<?php
declare(strict_types=1);
namespace App\Http\Controllers\Api\V1\Admin;

use App\Core\{Request, Response, Container, Validation, TenantContext, RefGenerator};
use App\Core\Security\PasswordHasher;
use App\Repositories\Contracts\UserRepositoryInterface;
use App\Domain\Audit\AuditService;
use App\Domain\Authorization\AuthorizationService;
use App\Core\Exceptions\{NotFoundException, ConflictException, ForbiddenException, ValidationException};

final class UsersController
{
    public function __construct(
        private UserRepositoryInterface $userRepo,
        private PasswordHasher $hasher,
        private AuditService $audit,
        private AuthorizationService $authorization,
        private \PDO $pdo,
    ) {}

    private function getTenantContext(): TenantContext
    {
        /** @var TenantContext $ctx */
        $ctx = Container::getInstance()->make(TenantContext::class);
        return $ctx;
    }

    public function index(Request $r): Response
    {
        $ctx = $this->getTenantContext();
        $this->authorization->requirePermission($ctx, 'internalUsers', 'view');
        $page    = (int) $r->query('page', '1');
        $perPage = (int) $r->query('per_page', '25');

        $filters = [
            'role'   => $r->query('role', ''),
            'status' => $r->query('status', ''),
            'search' => $r->query('search', ''),
        ];

        // Scope to franchise if not super admin
        if (!$ctx->isSuper()) {
            $filters['franchise_ref'] = $ctx->requireFranchise();
        } elseif ($r->has('franchise_ref')) {
            $filters['franchise_ref'] = $r->query('franchise_ref');
        }

        $res = $this->userRepo->list($filters, $page, $perPage);
        return Response::json(200, $res['data'], $res['meta']);
    }

    public function show(Request $r): Response
    {
        $ctx = $this->getTenantContext();
        $this->authorization->requirePermission($ctx, 'internalUsers', 'view');
        $ref = $r->param('ref');
        $user = $this->userRepo->findByRef($ref);
        if (!$user) {
            throw new NotFoundException("User {$ref} not found.");
        }

        if (!$ctx->isSuper() && $user['franchise_ref'] !== $ctx->franchiseRef) {
            throw new NotFoundException("User {$ref} not found.");
        }

        unset($user['password_hash']);
        $effective = $this->authorization->effectiveForUser($user['user_ref'], $user['franchise_ref']);
        $user['roles'] = $effective['roles'];
        $user['permissions'] = $effective['permissions'];
        $user['scopes'] = $effective['scopes'];
        return Response::json(200, $user);
    }

    public function create(Request $r): Response
    {
        $ctx = $this->getTenantContext();
        $this->authorization->requirePermission($ctx, 'internalUsers', 'create');
        $clean = Validation::validate($r->all(), [
            'full_name' => 'required|string',
            'email'     => 'required|email',
            'password'  => 'required|string',
        ]);

        $roleRef = (string)$r->input('role_ref', '');
        $legacyRole = $this->resolveRoleForCreate($ctx, $roleRef, (string)$r->input('role', 'SALES'));

        $franchiseRef = $ctx->isSuper() ? $r->input('franchise_ref') : $ctx->requireFranchise();
        if (!$franchiseRef) {
            throw new ValidationException('FRANCHISE_REQUIRED', 'Franchise reference required.');
        }

        $email = strtolower(trim($clean['email']));
        if ($this->userRepo->findByEmailAndTenant($email, $franchiseRef)) {
            throw new ConflictException('USER_EMAIL_EXISTS', "User with email {$email} already exists in this franchise.");
        }

        $userRef = RefGenerator::make('USR');
        $hash = $this->hasher->hash($clean['password']);

        $user = [
            'user_ref'             => $userRef,
            'org_ref'              => $ctx->orgRef,
            'franchise_ref'        => $franchiseRef,
            'role'                 => $legacyRole,
            'party_ref'            => $r->input('party_ref'),
            'full_name'            => trim($clean['full_name']),
            'email'                => $email,
            'mobile'               => $r->input('mobile'),
            'employee_code'       => $r->input('employee_code'),
            'department'          => $r->input('department'),
            'designation'         => $r->input('designation'),
            'assigned_region'     => $r->input('assigned_region'),
            'joining_date'        => $r->input('joining_date'),
            'password_hash'        => $hash,
            'must_change_password' => $r->input('must_change_password', 0) ? 1 : 0,
            'status'               => 'ACTIVE',
            'created_by_ref'       => $ctx->userRef,
            'created_at'           => date('Y-m-d H:i:s'),
        ];

        $this->userRepo->create($user);
        $this->setReportingManager($ctx, $userRef, $r->input('reporting_manager_ref'));
        if ($roleRef !== '') $this->assignNormalizedRole($ctx, $userRef, $roleRef);

        $this->audit->log(
            ctx: $ctx,
            category: 'BUSINESS',
            action: 'user.created',
            entityType: 'user',
            entityRef: $userRef,
            after: $user
        );

        unset($user['password_hash']);
        $effective = $this->authorization->effectiveForUser($userRef, $franchiseRef);
        $user['roles'] = $effective['roles'];
        $user['permissions'] = $effective['permissions'];
        $user['scopes'] = $effective['scopes'];
        return Response::json(201, $user);
    }

    public function update(Request $r): Response
    {
        $ctx = $this->getTenantContext();
        $this->authorization->requirePermission($ctx, 'internalUsers', 'edit');
        $ref = $r->param('ref');
        $user = $this->userRepo->findByRef($ref);
        if (!$user) {
            throw new NotFoundException("User {$ref} not found.");
        }

        if (!$ctx->isSuper() && $user['franchise_ref'] !== $ctx->franchiseRef) {
            throw new NotFoundException("User {$ref} not found.");
        }

        $updates = [];
        if ($r->has('full_name')) $updates['full_name'] = trim((string)$r->input('full_name'));
        if ($r->has('mobile'))    $updates['mobile'] = $r->input('mobile');
        if ($r->has('party_ref')) $updates['party_ref'] = $r->input('party_ref');
        foreach (['employee_code', 'department', 'designation', 'assigned_region', 'joining_date'] as $field) {
            if ($r->has($field)) $updates[$field] = $r->input($field);
        }
        if ($r->has('reporting_manager_ref')) $this->setReportingManager($ctx, $ref, $r->input('reporting_manager_ref'));

        if (!empty($updates)) {
            $this->userRepo->update($ref, $updates);
            $this->audit->log(
                ctx: $ctx,
                category: 'BUSINESS',
                action: 'user.updated',
                entityType: 'user',
                entityRef: $ref,
                before: $user,
                after: array_merge($user, $updates)
            );
        }

        $updated = $this->userRepo->findByRef($ref);
        unset($updated['password_hash']);
        return Response::json(200, $updated);
    }

    public function activate(Request $r): Response
    {
        $ctx = $this->getTenantContext();
        $this->authorization->requirePermission($ctx, 'internalUsers', 'activateDeactivate');
        $ref = $r->param('ref');
        $user = $this->userRepo->findByRef($ref);
        if (!$user) {
            throw new NotFoundException("User {$ref} not found.");
        }
        if (!$ctx->isSuper() && $user['franchise_ref'] !== $ctx->franchiseRef) {
            throw new NotFoundException("User {$ref} not found.");
        }

        $this->userRepo->setStatus($ref, 'ACTIVE');
        $this->audit->log(ctx: $ctx, category: 'BUSINESS', action: 'user.activated', entityType: 'user', entityRef: $ref);

        return Response::json(200, ['status' => 'ACTIVE']);
    }

    public function deactivate(Request $r): Response
    {
        $ctx = $this->getTenantContext();
        $this->authorization->requirePermission($ctx, 'internalUsers', 'activateDeactivate');
        $ref = $r->param('ref');
        $user = $this->userRepo->findByRef($ref);
        if (!$user) {
            throw new NotFoundException('USER_NOT_FOUND', "User {$ref} not found.");
        }
        if (!$ctx->isSuper() && $user['franchise_ref'] !== $ctx->franchiseRef) {
            throw new NotFoundException('USER_NOT_FOUND', "User {$ref} not found.");
        }

        $this->assertNotLastRoleManager($ctx, $ref);
        $this->userRepo->setStatus($ref, 'INACTIVE');
        $this->pdo->prepare("UPDATE user_sessions SET revoked_at = NOW(), revoke_reason = 'USER_DEACTIVATED' WHERE user_ref = :u AND revoked_at IS NULL")
            ->execute([':u' => $ref]);
        $this->audit->log(ctx: $ctx, category: 'BUSINESS', action: 'user.deactivated', entityType: 'user', entityRef: $ref);

        return Response::json(200, ['status' => 'INACTIVE']);
    }

    public function resetPassword(Request $r): Response
    {
        $ctx = $this->getTenantContext();
        $this->authorization->requirePermission($ctx, 'internalUsers', 'resetPassword');
        $ref = $r->param('ref');
        $user = $this->userRepo->findByRef($ref);
        if (!$user) {
            throw new NotFoundException('USER_NOT_FOUND', "User {$ref} not found.");
        }
        if (!$ctx->isSuper() && $user['franchise_ref'] !== $ctx->franchiseRef) {
            throw new NotFoundException('USER_NOT_FOUND', "User {$ref} not found.");
        }

        $clean = Validation::validate($r->all(), [
            'password' => 'required|string',
        ]);

        $hash = $this->hasher->hash($clean['password']);
        $this->userRepo->setPassword($ref, $hash, true);

        $this->audit->log(
            ctx: $ctx,
            category: 'SECURITY',
            action: 'user.password_reset',
            entityType: 'user',
            entityRef: $ref
        );

        return Response::json(200, ['reset' => true]);
    }

    public function unlock(Request $r): Response
    {
        $ctx = $this->getTenantContext();
        $this->authorization->requirePermission($ctx, 'internalUsers', 'activateDeactivate');
        $ref = $r->param('ref');
        $user = $this->userRepo->findByRef($ref);
        if (!$user) {
            throw new NotFoundException('USER_NOT_FOUND', "User {$ref} not found.");
        }
        if (!$ctx->isSuper() && $user['franchise_ref'] !== $ctx->franchiseRef) {
            throw new NotFoundException('USER_NOT_FOUND', "User {$ref} not found.");
        }

        $this->userRepo->unlock($ref);
        $this->audit->log(
            ctx: $ctx,
            category: 'SECURITY',
            action: 'user.unlocked',
            entityType: 'user',
            entityRef: $ref
        );

        return Response::json(200, ['unlocked' => true]);
    }

    private function resolveRoleForCreate(TenantContext $ctx, string $roleRef, string $legacyRole): string
    {
        if ($roleRef === '') {
            if (!in_array($legacyRole, ['SALES', 'DISTRIBUTOR', 'FRANCHISE_ADMIN'], true)) {
                throw new ValidationException('INVALID_ROLE', 'role_ref is required for configurable internal roles.');
            }
            return $legacyRole;
        }
        $stmt = $this->pdo->prepare("SELECT role_slug, status, franchise_ref FROM auth_roles WHERE role_ref=:r");
        $stmt->execute([':r' => $roleRef]);
        $role = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (!$role || (!$ctx->isSuper() && $role['franchise_ref'] !== $ctx->franchiseRef)) throw new ValidationException('INVALID_ROLE', 'Role is not available in this franchise.');
        if ($role['status'] !== 'ACTIVE') throw new ValidationException('ROLE_INACTIVE', 'Cannot assign an inactive role.');
        $this->authorization->assertCanGrantRole($ctx, $roleRef);
        if ($role['role_slug'] === 'admin') return 'FRANCHISE_ADMIN';
        return 'SALES';
    }

    private function assignNormalizedRole(TenantContext $ctx, string $userRef, string $roleRef): void
    {
        if ($userRef === $ctx->userRef) throw new ForbiddenException('OWN_ROLE_PROTECTED', 'You cannot assign a role to yourself.');
        $stmt = $this->pdo->prepare("INSERT INTO auth_user_roles (user_ref, role_ref, assigned_by_ref) VALUES (:u,:r,:a)");
        $stmt->execute([':u' => $userRef, ':r' => $roleRef, ':a' => $ctx->userRef]);
        $this->audit->log($ctx, 'SECURITY', 'user.role_assigned', 'user', $userRef, null, ['role_ref' => $roleRef]);
    }

    private function assertNotLastRoleManager(TenantContext $ctx, string $userRef): void
    {
        if ($userRef === $ctx->userRef) throw new ForbiddenException('SELF_DEACTIVATION', 'You cannot deactivate your own account.');
        if ($ctx->isSuper()) return;
        $stmt = $this->pdo->prepare(
            "SELECT COUNT(*) FROM auth_user_roles ur JOIN users u ON u.user_ref=ur.user_ref
             JOIN auth_role_permissions rp ON rp.role_ref=ur.role_ref JOIN auth_permissions p ON p.permission_ref=rp.permission_ref
             WHERE u.franchise_ref=:f AND u.status='ACTIVE' AND p.module_key='rolesAndPermissions' AND p.action_key='edit'
             AND ur.user_ref<>:u"
        );
        $stmt->execute([':f' => $ctx->requireFranchise(), ':u' => $userRef]);
        if ((int)$stmt->fetchColumn() === 0) throw new ForbiddenException('LAST_PRIVILEGED_USER', 'At least one active Roles and Permissions administrator must remain.');
    }

    private function setReportingManager(TenantContext $ctx, string $userRef, mixed $managerRef): void
    {
        if ($managerRef === null || $managerRef === '') {
            $this->pdo->prepare("DELETE FROM auth_user_hierarchy WHERE user_ref = :u")->execute([':u' => $userRef]);
            return;
        }
        if ((string)$managerRef === $userRef) throw new ValidationException('REPORTING_CYCLE', 'A user cannot report to themselves.');
        $franchiseRef = $ctx->isSuper()
            ? (string)$this->pdo->query("SELECT franchise_ref FROM users WHERE user_ref = " . $this->pdo->quote($userRef))->fetchColumn()
            : $ctx->requireFranchise();
        $stmt = $this->pdo->prepare("SELECT user_ref FROM users WHERE user_ref=:m AND franchise_ref=:f AND status='ACTIVE'");
        $stmt->execute([':m' => (string)$managerRef, ':f' => $franchiseRef]);
        if (!$stmt->fetchColumn()) throw new ValidationException('INVALID_MANAGER', 'Reporting manager is not an active user in this franchise.');
        $this->pdo->prepare(
            "INSERT INTO auth_user_hierarchy (user_ref, manager_ref, assigned_by_ref) VALUES (:u,:m,:a)
             ON DUPLICATE KEY UPDATE manager_ref=VALUES(manager_ref), assigned_by_ref=VALUES(assigned_by_ref)"
        )->execute([':u' => $userRef, ':m' => (string)$managerRef, ':a' => $ctx->userRef]);
    }
}
