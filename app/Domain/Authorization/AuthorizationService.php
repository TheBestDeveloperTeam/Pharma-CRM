<?php

declare(strict_types=1);

namespace App\Domain\Authorization;

use App\Core\Exceptions\{ForbiddenException, NotFoundException, ValidationException};
use App\Core\TenantContext;

/**
 * Central TASK-001 authorization resolver.
 *
 * The legacy users.role column remains a surface-compatibility field. This
 * service reads normalized auth_* tables and is the only place where role,
 * permission and module-scope resolution is performed.
 */
final class AuthorizationService
{
    public function __construct(private readonly \PDO $pdo) {}

    /** @return array{roles: array<int,array<string,mixed>>, permissions: array<string,array<int,string>>, scopes: array<string,string>, team_user_refs: array<int,string>, territory_refs: array<int,string>} */
    public function effectiveForUser(string $userRef, ?string $franchiseRef = null): array
    {
        try {
            $roleSql = "SELECT r.role_ref, r.role_name, r.role_slug, r.is_system, r.status,
                               r.default_scope
                        FROM auth_user_roles ur
                        JOIN auth_roles r ON r.role_ref = ur.role_ref
                        WHERE ur.user_ref = :u AND r.status = 'ACTIVE'";
            $params = [':u' => $userRef];
            if ($franchiseRef !== null) {
                $roleSql .= " AND r.franchise_ref = :f";
                $params[':f'] = $franchiseRef;
            }
            $stmt = $this->pdo->prepare($roleSql);
            $stmt->execute($params);
            $roles = $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];

            $permissions = [];
            $scopes = [];
            foreach ($roles as $role) {
                $scopeStmt = $this->pdo->prepare(
                    "SELECT module_key, data_scope FROM auth_role_scopes WHERE role_ref = :r"
                );
                $scopeStmt->execute([':r' => $role['role_ref']]);
                foreach ($scopeStmt->fetchAll(\PDO::FETCH_ASSOC) as $scope) {
                    $scopes[$scope['module_key']] = $this->broaderScope($scopes[$scope['module_key']] ?? 'NONE', $scope['data_scope']);
                }
                $defaultScope = (string)$role['default_scope'];

                $permissionStmt = $this->pdo->prepare(
                    "SELECT p.module_key, p.action_key
                     FROM auth_role_permissions rp
                     JOIN auth_permissions p ON p.permission_ref = rp.permission_ref
                     WHERE rp.role_ref = :r"
                );
                $permissionStmt->execute([':r' => $role['role_ref']]);
                foreach ($permissionStmt->fetchAll(\PDO::FETCH_ASSOC) as $permission) {
                    $permissions[$permission['module_key']][] = $permission['action_key'];
                    if (!isset($scopes[$permission['module_key']])) {
                        $scopes[$permission['module_key']] = $defaultScope;
                    }
                }
            }

            foreach ($permissions as $module => $actions) {
                $permissions[$module] = array_values(array_unique($actions));
            }

            $teamStmt = $this->pdo->prepare("SELECT user_ref FROM auth_user_hierarchy WHERE manager_ref = :u");
            $teamStmt->execute([':u' => $userRef]);
            $territoryStmt = $this->pdo->prepare("SELECT territory_ref FROM auth_user_territories WHERE user_ref = :u");
            $territoryStmt->execute([':u' => $userRef]);
            return [
                'roles' => $roles,
                'permissions' => $permissions,
                'scopes' => $scopes,
                'team_user_refs' => array_column($teamStmt->fetchAll(\PDO::FETCH_ASSOC), 'user_ref'),
                'territory_refs' => array_column($territoryStmt->fetchAll(\PDO::FETCH_ASSOC), 'territory_ref'),
            ];
        } catch (\PDOException) {
            // Safe compatibility fallback while the additive migration is being deployed.
            return ['roles' => [], 'permissions' => [], 'scopes' => [], 'team_user_refs' => [], 'territory_refs' => []];
        }
    }

    public function can(TenantContext $ctx, string $module, string $action): bool
    {
        if ($ctx->isSuper()) return true;
        return in_array($action, $ctx->permissions[$module] ?? [], true);
    }

    public function requirePermission(TenantContext $ctx, string $module, string $action): void
    {
        if (!$this->can($ctx, $module, $action)) {
            throw new ForbiddenException('FORBIDDEN_PERMISSION', "Permission required: {$module}.{$action}");
        }
    }

    public function requireRecordScope(
        TenantContext $ctx,
        string $module,
        ?string $ownerRef = null,
        ?string $territoryRef = null,
        ?string $recordFranchiseRef = null,
    ): void {
        if ($ctx->isSuper()) return;
        if ($recordFranchiseRef !== null && $recordFranchiseRef !== $ctx->franchiseRef) {
            throw new NotFoundException('RECORD_NOT_FOUND', 'Record not found.');
        }

        $scope = $ctx->scopes[$module] ?? 'NONE';
        if ($scope === 'ALL') return;
        if ($scope === 'OWN' && $ownerRef === $ctx->userRef) return;
        if ($scope === 'TEAM' && ($ownerRef === $ctx->userRef || in_array($ownerRef, $ctx->teamUserRefs, true))) return;
        if ($scope === 'TERRITORY' && $territoryRef !== null && in_array($territoryRef, $ctx->territoryRefs, true)) return;

        throw new NotFoundException('RECORD_NOT_FOUND', 'Record not found.');
    }

    public function validatePermissionKeys(array $permissions): array
    {
        $validated = [];
        foreach ($permissions as $module => $actions) {
            if (!is_string($module) || !is_array($actions)) {
                throw new ValidationException('INVALID_PERMISSIONS', 'Permission payload must be module-to-action arrays.');
            }
            $stmt = $this->pdo->prepare(
                "SELECT action_key FROM auth_permission_catalogue WHERE module_key = :m"
            );
            $stmt->execute([':m' => $module]);
            $allowed = array_column($stmt->fetchAll(\PDO::FETCH_ASSOC), 'action_key');
            $requested = array_values(array_unique(array_map('strval', $actions)));
            $invalid = array_values(array_diff($requested, $allowed));
            if ($invalid) {
                throw new ValidationException('INVALID_PERMISSION', "Unknown permission(s) for {$module}: " . implode(', ', $invalid));
            }
            $validated[$module] = $requested;
        }
        return $validated;
    }

    public function assertCanGrantRole(TenantContext $ctx, string $roleRef): void
    {
        if ($ctx->isSuper()) return;
        $stmt = $this->pdo->prepare(
            "SELECT p.module_key, p.action_key
             FROM auth_role_permissions rp JOIN auth_permissions p ON p.permission_ref = rp.permission_ref
             WHERE rp.role_ref = :r"
        );
        $stmt->execute([':r' => $roleRef]);
        foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $permission) {
            if (!$ctx->can($permission['module_key'], $permission['action_key'])) {
                throw new ForbiddenException('PERMISSION_ESCALATION', "Cannot grant {$permission['module_key']}.{$permission['action_key']}.");
            }
        }
        $scopeStmt = $this->pdo->prepare("SELECT module_key, data_scope FROM auth_role_scopes WHERE role_ref = :r");
        $scopeStmt->execute([':r' => $roleRef]);
        $rank = ['NONE' => 0, 'OWN' => 1, 'TEAM' => 2, 'TERRITORY' => 3, 'ALL' => 4];
        foreach ($scopeStmt->fetchAll(\PDO::FETCH_ASSOC) as $scope) {
            if (($rank[$scope['data_scope']] ?? 0) > ($rank[$ctx->scopeFor($scope['module_key'])] ?? 0)) {
                throw new ForbiddenException('SCOPE_ESCALATION', "Cannot grant {$scope['data_scope']} scope for {$scope['module_key']}.");
            }
        }
    }

    private function broaderScope(string $current, string $candidate): string
    {
        $rank = ['NONE' => 0, 'OWN' => 1, 'TEAM' => 2, 'TERRITORY' => 3, 'ALL' => 4];
        return (($rank[$candidate] ?? 0) > ($rank[$current] ?? 0)) ? $candidate : $current;
    }
}
