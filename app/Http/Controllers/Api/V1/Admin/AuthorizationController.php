<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Core\{Container, Request, Response, RefGenerator, TenantContext, Validation};
use App\Core\Exceptions\{ConflictException, ForbiddenException, NotFoundException, ValidationException};
use App\Domain\Audit\AuditService;
use App\Domain\Authorization\AuthorizationService;

/** TASK-001 role, permission, scope and role-assignment API. */
final class AuthorizationController
{
    private const SCOPES = ['ALL', 'TERRITORY', 'TEAM', 'OWN', 'NONE'];
    private const SCOPE_RANK = ['NONE' => 0, 'OWN' => 1, 'TEAM' => 2, 'TERRITORY' => 3, 'ALL' => 4];

    public function __construct(
        private readonly \PDO $pdo,
        private readonly AuthorizationService $authorization,
        private readonly AuditService $audit,
    ) {}

    private function context(string $module = 'rolesAndPermissions', string $action = 'view'): TenantContext
    {
        /** @var TenantContext $ctx */
        $ctx = Container::getInstance()->make(TenantContext::class);
        $this->authorization->requirePermission($ctx, $module, $action);
        return $ctx;
    }

    public function roles(Request $r): Response
    {
        $ctx = $this->context();
        $where = ['org_ref = :o'];
        $params = [':o' => $ctx->orgRef];
        if (!$ctx->isSuper()) {
            $where[] = 'franchise_ref = :f';
            $params[':f'] = $ctx->requireFranchise();
        } elseif ($r->query('franchise_ref')) {
            $where[] = 'franchise_ref = :f';
            $params[':f'] = (string)$r->query('franchise_ref');
        }
        $whereSql = implode(' AND ', $where);
        $rows = $this->pdo->prepare(
            "SELECT r.role_ref, r.role_name, r.role_slug, r.description, r.is_system, r.status,
                    r.default_scope, COUNT(ur.user_ref) AS assigned_user_count
             FROM auth_roles r LEFT JOIN auth_user_roles ur ON ur.role_ref = r.role_ref
             WHERE {$whereSql} GROUP BY r.role_ref ORDER BY r.role_name"
        );
        $rows->execute($params);
        return Response::json(200, $rows->fetchAll(\PDO::FETCH_ASSOC));
    }

    public function role(Request $r): Response
    {
        $ctx = $this->context();
        $role = $this->findRole($ctx, (string)$r->param('ref'));
        $role['permissions'] = $this->permissionsForRole($role['role_ref']);
        $role['scope_overrides'] = $this->scopesForRole($role['role_ref']);
        $role['assigned_user_count'] = (int)$this->pdo->query(
            "SELECT COUNT(*) FROM auth_user_roles WHERE role_ref = " . $this->pdo->quote($role['role_ref'])
        )->fetchColumn();
        return Response::json(200, $role);
    }

    public function permissionCatalogue(Request $r): Response
    {
        $this->context();
        $stmt = $this->pdo->query(
            "SELECT module_key, action_key, label, is_sensitive
             FROM auth_permission_catalogue ORDER BY module_key, action_key"
        );
        return Response::json(200, $stmt->fetchAll(\PDO::FETCH_ASSOC));
    }

    public function createRole(Request $r): Response
    {
        $ctx = $this->context('rolesAndPermissions', 'create');
        $input = Validation::validate($r->all(), [
            'name' => 'required|string|max:120',
            'description' => 'nullable|string|max:500',
            'default_scope' => 'required|string',
        ]);
        $this->validateScope((string)$input['default_scope']);
        $permissions = $this->validatedPermissions($r->input('permissions', []), $ctx);
        $this->validateDefaultScope((string)$input['default_scope'], $permissions, $ctx);
        $scopes = $this->validatedScopes($r->input('scope_overrides', []), $ctx);
        $slug = $this->slug((string)$input['name']);
        $franchiseRef = $ctx->requireFranchise();
        $this->assertUniqueSlug($franchiseRef, $slug);
        $roleRef = RefGenerator::make('ROL');

        $this->pdo->beginTransaction();
        try {
            $stmt = $this->pdo->prepare(
                "INSERT INTO auth_roles
                 (role_ref, org_ref, franchise_ref, role_name, role_slug, description, is_system, status, default_scope, created_by_ref)
                 VALUES (:rr,:o,:f,:n,:s,:d,0,'ACTIVE',:scope,:actor)"
            );
            $stmt->execute([
                ':rr' => $roleRef, ':o' => $ctx->orgRef, ':f' => $franchiseRef,
                ':n' => trim((string)$input['name']), ':s' => $slug,
                ':d' => $input['description'] ?? null, ':scope' => strtoupper((string)$input['default_scope']),
                ':actor' => $ctx->userRef,
            ]);
            $this->replaceRoleGrants($roleRef, $permissions, $scopes, $ctx);
            $this->pdo->commit();
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            throw $e;
        }

        $this->audit->log($ctx, 'SECURITY', 'role.created', 'role', $roleRef, null, [
            'role_ref' => $roleRef, 'name' => $input['name'], 'permissions' => $permissions, 'scopes' => $scopes,
        ]);
        return Response::json(201, $this->rolePayload($ctx, $roleRef));
    }

    public function updateRole(Request $r): Response
    {
        $ctx = $this->context('rolesAndPermissions', 'edit');
        $roleRef = (string)$r->param('ref');
        $role = $this->findRole($ctx, $roleRef);
        $this->assertMutableRole($ctx, $role);
        $before = $this->rolePayload($ctx, $roleRef);
        $data = $r->all();

        $name = array_key_exists('name', $data) ? trim((string)$data['name']) : $role['role_name'];
        if ($name === '') throw new ValidationException('VALIDATION_FAILED', 'Role name is required.', ['name' => ['This field is required.']]);
        $slug = $this->slug($name);
        if ($slug !== $role['role_slug']) $this->assertUniqueSlug((string)$role['franchise_ref'], $slug, $roleRef);
        $defaultScope = strtoupper((string)($data['default_scope'] ?? $role['default_scope']));
        $this->validateScope($defaultScope);
        $permissions = array_key_exists('permissions', $data) ? $this->validatedPermissions($data['permissions'], $ctx) : $this->permissionsForRole($roleRef);
        $scopes = array_key_exists('scope_overrides', $data) ? $this->validatedScopes($data['scope_overrides'], $ctx) : $this->scopesForRole($roleRef);
        $this->validateDefaultScope($defaultScope, $permissions, $ctx);
        $status = strtoupper((string)($data['status'] ?? $role['status']));
        if (!in_array($status, ['ACTIVE', 'INACTIVE'], true)) throw new ValidationException('INVALID_STATUS', 'Role status must be ACTIVE or INACTIVE.');
        if ($status === 'INACTIVE') $this->assertNotLastPrivilegedRole($roleRef, $ctx);

        $this->pdo->beginTransaction();
        try {
            $stmt = $this->pdo->prepare(
                "UPDATE auth_roles SET role_name=:n, role_slug=:s, description=:d, status=:st, default_scope=:scope,
                 updated_by_ref=:actor, updated_at=NOW() WHERE role_ref=:rr"
            );
            $stmt->execute([
                ':n' => $name, ':s' => $slug, ':d' => $data['description'] ?? $role['description'],
                ':st' => $status, ':scope' => $defaultScope, ':actor' => $ctx->userRef, ':rr' => $roleRef,
            ]);
            if (array_key_exists('permissions', $data) || array_key_exists('scope_overrides', $data)) {
                $this->replaceRoleGrants($roleRef, $permissions, $scopes, $ctx);
            }
            $this->pdo->commit();
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            throw $e;
        }
        $after = $this->rolePayload($ctx, $roleRef);
        $this->audit->log($ctx, 'SECURITY', 'role.updated', 'role', $roleRef, $before, $after);
        return Response::json(200, $after);
    }

    public function cloneRole(Request $r): Response
    {
        $ctx = $this->context('rolesAndPermissions', 'create');
        $source = $this->findRole($ctx, (string)$r->param('ref'));
        $name = trim((string)$r->input('name', 'Copy of ' . $source['role_name']));
        if ($name === '') throw new ValidationException('VALIDATION_FAILED', 'Role name is required.');
        $slug = $this->slug($name);
        $this->assertUniqueSlug($ctx->requireFranchise(), $slug);
        $roleRef = RefGenerator::make('ROL');
        $this->pdo->beginTransaction();
        try {
            $stmt = $this->pdo->prepare(
                "INSERT INTO auth_roles (role_ref,org_ref,franchise_ref,role_name,role_slug,description,is_system,status,default_scope,created_by_ref)
                 VALUES (:rr,:o,:f,:n,:s,:d,0,'ACTIVE',:scope,:actor)"
            );
            $stmt->execute([
                ':rr' => $roleRef, ':o' => $ctx->orgRef, ':f' => $ctx->requireFranchise(), ':n' => $name,
                ':s' => $slug, ':d' => $source['description'], ':scope' => $source['default_scope'], ':actor' => $ctx->userRef,
            ]);
            $sourcePermissions = $this->permissionsForRole((string)$source['role_ref']);
            $sourceScopes = $this->scopesForRole((string)$source['role_ref']);
            $sourcePermissions = $this->validatedPermissions($sourcePermissions, $ctx);
            $sourceScopes = $this->validatedScopes($sourceScopes, $ctx);
            $this->validateDefaultScope((string)$source['default_scope'], $sourcePermissions, $ctx);
            $this->replaceRoleGrants($roleRef, $sourcePermissions, $sourceScopes, $ctx);
            $this->pdo->commit();
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            throw $e;
        }
        $this->audit->log($ctx, 'SECURITY', 'role.cloned', 'role', $roleRef, null, ['source_role_ref' => $source['role_ref']]);
        return Response::json(201, $this->rolePayload($ctx, $roleRef));
    }

    public function deleteRole(Request $r): Response
    {
        $ctx = $this->context('rolesAndPermissions', 'delete');
        $roleRef = (string)$r->param('ref');
        $role = $this->findRole($ctx, $roleRef);
        $this->assertMutableRole($ctx, $role);
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM auth_user_roles WHERE role_ref = :r");
        $stmt->execute([':r' => $roleRef]);
        if ((int)$stmt->fetchColumn() > 0) throw new ConflictException('ROLE_IN_USE', 'Reassign users before deleting this role.');
        $this->pdo->beginTransaction();
        try {
            $this->pdo->prepare("DELETE FROM auth_role_permissions WHERE role_ref = :r")->execute([':r' => $roleRef]);
            $this->pdo->prepare("DELETE FROM auth_role_scopes WHERE role_ref = :r")->execute([':r' => $roleRef]);
            $this->pdo->prepare("DELETE FROM auth_roles WHERE role_ref = :r")->execute([':r' => $roleRef]);
            $this->pdo->commit();
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            throw $e;
        }
        $this->audit->log($ctx, 'SECURITY', 'role.deleted', 'role', $roleRef, $role, null);
        return Response::json(200, ['deleted' => true, 'role_ref' => $roleRef]);
    }

    public function assignUserRole(Request $r): Response
    {
        $ctx = $this->context('rolesAndPermissions', 'assignToUser');
        $userRef = (string)$r->param('ref');
        $roleRef = (string)$r->input('role_ref');
        if ($roleRef === '') throw new ValidationException('ROLE_REQUIRED', 'role_ref is required.');
        $user = $this->findUserInTenant($ctx, $userRef);
        $role = $this->findRole($ctx, $roleRef);
        $this->authorization->assertCanGrantRole($ctx, $roleRef);
        if ($userRef === $ctx->userRef) throw new ForbiddenException('OWN_ROLE_PROTECTED', 'You cannot assign a role to yourself.');
        $this->assertGrantableRole($ctx, $roleRef);
        $stmt = $this->pdo->prepare("INSERT IGNORE INTO auth_user_roles (user_ref, role_ref, assigned_by_ref) VALUES (:u,:r,:a)");
        $stmt->execute([':u' => $userRef, ':r' => $roleRef, ':a' => $ctx->userRef]);
        $this->audit->log($ctx, 'SECURITY', 'user.role_assigned', 'user', $userRef, null, ['role_ref' => $roleRef]);
        return Response::json(200, ['user_ref' => $userRef, 'role_ref' => $roleRef]);
    }

    public function revokeUserRole(Request $r): Response
    {
        $ctx = $this->context('rolesAndPermissions', 'assignToUser');
        $userRef = (string)$r->param('ref');
        $roleRef = (string)$r->param('role_ref');
        if ($userRef === $ctx->userRef) throw new ForbiddenException('OWN_ROLE_PROTECTED', 'You cannot change your own role assignment.');
        $this->findUserInTenant($ctx, $userRef);
        $this->findRole($ctx, $roleRef);
        $this->assertNotLastPrivilegedAssignment($userRef, $roleRef, $ctx);
        $stmt = $this->pdo->prepare("DELETE FROM auth_user_roles WHERE user_ref = :u AND role_ref = :r");
        $stmt->execute([':u' => $userRef, ':r' => $roleRef]);
        $this->audit->log($ctx, 'SECURITY', 'user.role_revoked', 'user', $userRef, ['role_ref' => $roleRef], null);
        return Response::json(200, ['user_ref' => $userRef, 'role_ref' => $roleRef, 'revoked' => true]);
    }

    private function findRole(TenantContext $ctx, string $roleRef): array
    {
        $sql = "SELECT * FROM auth_roles WHERE role_ref = :r AND org_ref = :o";
        $params = [':r' => $roleRef, ':o' => $ctx->orgRef];
        if (!$ctx->isSuper()) { $sql .= " AND franchise_ref = :f"; $params[':f'] = $ctx->requireFranchise(); }
        $stmt = $this->pdo->prepare($sql . ' LIMIT 1');
        $stmt->execute($params);
        $role = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (!$role) throw new NotFoundException('ROLE_NOT_FOUND');
        return $role;
    }

    private function findUserInTenant(TenantContext $ctx, string $userRef): array
    {
        $sql = "SELECT * FROM users WHERE user_ref = :u AND org_ref = :o";
        $params = [':u' => $userRef, ':o' => $ctx->orgRef];
        if (!$ctx->isSuper()) { $sql .= " AND franchise_ref = :f"; $params[':f'] = $ctx->requireFranchise(); }
        $stmt = $this->pdo->prepare($sql . ' LIMIT 1'); $stmt->execute($params);
        $user = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (!$user) throw new NotFoundException('USER_NOT_FOUND');
        return $user;
    }

    private function permissionsForRole(string $roleRef): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT p.module_key, p.action_key FROM auth_role_permissions rp
             JOIN auth_permissions p ON p.permission_ref = rp.permission_ref WHERE rp.role_ref = :r ORDER BY p.module_key,p.action_key"
        ); $stmt->execute([':r' => $roleRef]);
        $out = [];
        foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $row) $out[$row['module_key']][] = $row['action_key'];
        return $out;
    }

    private function scopesForRole(string $roleRef): array
    {
        $stmt = $this->pdo->prepare("SELECT module_key, data_scope FROM auth_role_scopes WHERE role_ref = :r");
        $stmt->execute([':r' => $roleRef]);
        return array_column($stmt->fetchAll(\PDO::FETCH_ASSOC), 'data_scope', 'module_key');
    }

    private function rolePayload(TenantContext $ctx, string $roleRef): array
    {
        $role = $this->findRole($ctx, $roleRef);
        $role['permissions'] = $this->permissionsForRole($roleRef);
        $role['scope_overrides'] = $this->scopesForRole($roleRef);
        return $role;
    }

    private function validatedPermissions(mixed $permissions, TenantContext $ctx): array
    {
        if (!is_array($permissions)) throw new ValidationException('INVALID_PERMISSIONS', 'permissions must be a module-to-actions object.');
        $validated = $this->authorization->validatePermissionKeys($permissions);
        foreach ($validated as $module => $actions) foreach ($actions as $action) {
            if (!$ctx->isSuper() && !$ctx->can($module, $action)) throw new ForbiddenException('PERMISSION_ESCALATION', "Cannot grant {$module}.{$action}.");
        }
        return $validated;
    }

    private function validatedScopes(mixed $scopes, TenantContext $ctx): array
    {
        if (!is_array($scopes)) throw new ValidationException('INVALID_SCOPES', 'scope_overrides must be an object.');
        $out = [];
        foreach ($scopes as $module => $scope) {
            $moduleStmt = $this->pdo->prepare("SELECT 1 FROM auth_permission_catalogue WHERE module_key = :m LIMIT 1");
            $moduleStmt->execute([':m' => (string)$module]);
            if (!$moduleStmt->fetchColumn()) throw new ValidationException('INVALID_SCOPE_MODULE', "Unknown scope module {$module}.");
            $scope = strtoupper((string)$scope);
            $this->validateScope($scope);
            if (!$ctx->isSuper() && (self::SCOPE_RANK[$scope] ?? 0) > (self::SCOPE_RANK[$ctx->scopeFor((string)$module)] ?? 0)) {
                throw new ForbiddenException('SCOPE_ESCALATION', "Cannot grant {$scope} scope for {$module}.");
            }
            $out[(string)$module] = $scope;
        }
        return $out;
    }

    private function validateDefaultScope(string $scope, array $permissions, TenantContext $ctx): void
    {
        if ($ctx->isSuper()) return;
        foreach (array_keys($permissions) as $module) {
            if ((self::SCOPE_RANK[$scope] ?? 0) > (self::SCOPE_RANK[$ctx->scopeFor((string)$module)] ?? 0)) {
                throw new ForbiddenException('SCOPE_ESCALATION', "Cannot grant {$scope} scope for {$module}.");
            }
        }
    }

    private function replaceRoleGrants(string $roleRef, array $permissions, array $scopes, TenantContext $ctx): void
    {
        $this->pdo->prepare("DELETE FROM auth_role_permissions WHERE role_ref = :r")->execute([':r' => $roleRef]);
        $this->pdo->prepare("DELETE FROM auth_role_scopes WHERE role_ref = :r")->execute([':r' => $roleRef]);
        $permissionStmt = $this->pdo->prepare("SELECT permission_ref FROM auth_permissions WHERE module_key = :m AND action_key = :a");
        $grantStmt = $this->pdo->prepare("INSERT INTO auth_role_permissions (role_ref, permission_ref, granted_by_ref) VALUES (:r,:p,:u)");
        foreach ($permissions as $module => $actions) foreach ($actions as $action) {
            $permissionStmt->execute([':m' => $module, ':a' => $action]);
            $permissionRef = $permissionStmt->fetchColumn();
            if (!$permissionRef) throw new ValidationException('INVALID_PERMISSION', "Unknown permission {$module}.{$action}.");
            $grantStmt->execute([':r' => $roleRef, ':p' => $permissionRef, ':u' => $ctx->userRef]);
        }
        $scopeStmt = $this->pdo->prepare("INSERT INTO auth_role_scopes (role_ref, module_key, data_scope) VALUES (:r,:m,:s)");
        foreach ($scopes as $module => $scope) $scopeStmt->execute([':r' => $roleRef, ':m' => $module, ':s' => $scope]);
    }

    private function assertMutableRole(TenantContext $ctx, array $role): void
    {
        if ((int)$role['is_system'] === 1) throw new ForbiddenException('SYSTEM_ROLE_PROTECTED', 'The Admin system role cannot be changed.');
        $stmt = $this->pdo->prepare("SELECT 1 FROM auth_user_roles WHERE user_ref = :u AND role_ref = :r LIMIT 1");
        $stmt->execute([':u' => $ctx->userRef, ':r' => $role['role_ref']]);
        if ($stmt->fetchColumn()) throw new ForbiddenException('OWN_ROLE_PROTECTED', 'You cannot modify your own role.');
    }

    private function assertGrantableRole(TenantContext $ctx, string $roleRef): void
    {
        $stmt = $this->pdo->prepare("SELECT is_system, status FROM auth_roles WHERE role_ref = :r"); $stmt->execute([':r' => $roleRef]);
        $role = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (!$role) throw new NotFoundException('ROLE_NOT_FOUND');
        if ($role['status'] !== 'ACTIVE') throw new ForbiddenException('ROLE_INACTIVE', 'Cannot assign an inactive role.');
    }

    private function assertNotLastPrivilegedRole(string $roleRef, TenantContext $ctx): void
    {
        if ($ctx->isSuper()) return;
        $stmt = $this->pdo->prepare(
            "SELECT COUNT(*) FROM auth_user_roles ur JOIN auth_roles r ON r.role_ref=ur.role_ref
             JOIN auth_role_permissions rp ON rp.role_ref=r.role_ref JOIN auth_permissions p ON p.permission_ref=rp.permission_ref
             JOIN users u ON u.user_ref=ur.user_ref WHERE u.franchise_ref=:f AND u.status='ACTIVE'
             AND p.module_key='rolesAndPermissions' AND p.action_key='edit' AND r.role_ref<>:r"
        ); $stmt->execute([':f' => $ctx->requireFranchise(), ':r' => $roleRef]);
        if ((int)$stmt->fetchColumn() === 0) throw new ForbiddenException('LAST_PRIVILEGED_USER', 'At least one active Roles and Permissions administrator must remain.');
    }

    private function assertNotLastPrivilegedAssignment(string $userRef, string $roleRef, TenantContext $ctx): void
    {
        $stmt = $this->pdo->prepare("SELECT 1 FROM auth_role_permissions WHERE role_ref=:r AND permission_ref=(SELECT permission_ref FROM auth_permissions WHERE module_key='rolesAndPermissions' AND action_key='edit')");
        $stmt->execute([':r' => $roleRef]);
        if (!$stmt->fetchColumn()) return;
        $count = $this->pdo->prepare(
            "SELECT COUNT(*) FROM auth_user_roles ur JOIN users u ON u.user_ref=ur.user_ref
             JOIN auth_role_permissions rp ON rp.role_ref=ur.role_ref JOIN auth_permissions p ON p.permission_ref=rp.permission_ref
             WHERE u.franchise_ref=:f AND u.status='ACTIVE' AND p.module_key='rolesAndPermissions' AND p.action_key='edit'
             AND NOT (ur.user_ref=:u AND ur.role_ref=:r)"
        ); $count->execute([':f' => $ctx->requireFranchise(), ':u' => $userRef, ':r' => $roleRef]);
        if ((int)$count->fetchColumn() === 0) throw new ForbiddenException('LAST_PRIVILEGED_USER', 'Cannot remove the last active Roles and Permissions administrator.');
    }

    private function validateScope(string $scope): void
    {
        if (!in_array($scope, self::SCOPES, true)) throw new ValidationException('INVALID_SCOPE', 'Scope must be ALL, TERRITORY, TEAM, OWN or NONE.');
    }

    private function assertUniqueSlug(string $franchiseRef, string $slug, ?string $except = null): void
    {
        $sql = "SELECT role_ref FROM auth_roles WHERE franchise_ref=:f AND role_slug=:s"; $params = [':f' => $franchiseRef, ':s' => $slug];
        if ($except) { $sql .= ' AND role_ref<>:r'; $params[':r'] = $except; }
        $stmt = $this->pdo->prepare($sql . ' LIMIT 1'); $stmt->execute($params);
        if ($stmt->fetchColumn()) throw new ConflictException('ROLE_SLUG_EXISTS', 'A role with this name already exists.');
    }

    private function slug(string $name): string
    {
        $slug = strtolower(trim((string)preg_replace('/[^a-z0-9]+/i', '-', $name), '-'));
        return $slug !== '' ? $slug : 'role-' . bin2hex(random_bytes(4));
    }
}
