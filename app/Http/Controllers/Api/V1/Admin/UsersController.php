<?php
declare(strict_types=1);
namespace App\Http\Controllers\Api\V1\Admin;

use App\Core\{Request, Response, Container, Validation, TenantContext, RefGenerator};
use App\Core\Security\PasswordHasher;
use App\Repositories\Contracts\UserRepositoryInterface;
use App\Domain\Audit\AuditService;
use App\Core\Exceptions\{NotFoundException, ConflictException, ForbiddenException, ValidationException};

final class UsersController
{
    public function __construct(
        private UserRepositoryInterface $userRepo,
        private PasswordHasher $hasher,
        private AuditService $audit,
    ) {}

    private function getTenantContext(): TenantContext
    {
        /** @var TenantContext $ctx */
        $ctx = Container::getInstance()->make(TenantContext::class);
        if (!$ctx->isAdmin() && !$ctx->isSuper()) {
            throw new ForbiddenException('FORBIDDEN', 'Only Franchise Admin or Super Admin can manage users.');
        }
        return $ctx;
    }

    public function index(Request $r): Response
    {
        $ctx = $this->getTenantContext();
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
        $ref = $r->param('ref');
        $user = $this->userRepo->findByRef($ref);
        if (!$user) {
            throw new NotFoundException('USER_NOT_FOUND', "User {$ref} not found.");
        }

        if (!$ctx->isSuper() && $user['franchise_ref'] !== $ctx->franchiseRef) {
            throw new NotFoundException('USER_NOT_FOUND', "User {$ref} not found.");
        }

        unset($user['password_hash']);
        return Response::json(200, $user);
    }

    public function create(Request $r): Response
    {
        $ctx = $this->getTenantContext();
        $clean = Validation::validate($r->all(), [
            'role'      => 'required|string',
            'full_name' => 'required|string',
            'email'     => 'required|email',
            'password'  => 'required|string',
        ]);

        $role = $clean['role'];
        if (!in_array($role, ['SALES', 'DISTRIBUTOR', 'FRANCHISE_ADMIN'], true)) {
            throw new ValidationException('INVALID_ROLE', 'Invalid user role specified.');
        }

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
            'role'                 => $role,
            'party_ref'            => $r->input('party_ref'),
            'full_name'            => trim($clean['full_name']),
            'email'                => $email,
            'mobile'               => $r->input('mobile'),
            'password_hash'        => $hash,
            'must_change_password' => $r->input('must_change_password', 0) ? 1 : 0,
            'status'               => 'ACTIVE',
            'created_by_ref'       => $ctx->userRef,
            'created_at'           => date('Y-m-d H:i:s'),
        ];

        $this->userRepo->create($user);

        $this->audit->log(
            ctx: $ctx,
            category: 'BUSINESS',
            action: 'user.created',
            entityType: 'user',
            entityRef: $userRef,
            after: $user
        );

        unset($user['password_hash']);
        return Response::json(201, $user);
    }

    public function update(Request $r): Response
    {
        $ctx = $this->getTenantContext();
        $ref = $r->param('ref');
        $user = $this->userRepo->findByRef($ref);
        if (!$user) {
            throw new NotFoundException('USER_NOT_FOUND', "User {$ref} not found.");
        }

        if (!$ctx->isSuper() && $user['franchise_ref'] !== $ctx->franchiseRef) {
            throw new NotFoundException('USER_NOT_FOUND', "User {$ref} not found.");
        }

        $updates = [];
        if ($r->has('full_name')) $updates['full_name'] = trim((string)$r->input('full_name'));
        if ($r->has('mobile'))    $updates['mobile'] = $r->input('mobile');
        if ($r->has('party_ref')) $updates['party_ref'] = $r->input('party_ref');

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
        $ref = $r->param('ref');
        $user = $this->userRepo->findByRef($ref);
        if (!$user) {
            throw new NotFoundException('USER_NOT_FOUND', "User {$ref} not found.");
        }
        if (!$ctx->isSuper() && $user['franchise_ref'] !== $ctx->franchiseRef) {
            throw new NotFoundException('USER_NOT_FOUND', "User {$ref} not found.");
        }

        $this->userRepo->setStatus($ref, 'ACTIVE');
        $this->audit->log(ctx: $ctx, category: 'BUSINESS', action: 'user.activated', entityType: 'user', entityRef: $ref);

        return Response::json(200, ['status' => 'ACTIVE']);
    }

    public function deactivate(Request $r): Response
    {
        $ctx = $this->getTenantContext();
        $ref = $r->param('ref');
        $user = $this->userRepo->findByRef($ref);
        if (!$user) {
            throw new NotFoundException('USER_NOT_FOUND', "User {$ref} not found.");
        }
        if (!$ctx->isSuper() && $user['franchise_ref'] !== $ctx->franchiseRef) {
            throw new NotFoundException('USER_NOT_FOUND', "User {$ref} not found.");
        }

        $this->userRepo->setStatus($ref, 'INACTIVE');
        $this->audit->log(ctx: $ctx, category: 'BUSINESS', action: 'user.deactivated', entityType: 'user', entityRef: $ref);

        return Response::json(200, ['status' => 'INACTIVE']);
    }

    public function resetPassword(Request $r): Response
    {
        $ctx = $this->getTenantContext();
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
}
