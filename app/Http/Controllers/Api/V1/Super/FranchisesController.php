<?php
declare(strict_types=1);
namespace App\Http\Controllers\Api\V1\Super;

use App\Core\{Request, Response, Container, Validation, TenantContext, RefGenerator};
use App\Core\Security\PasswordHasher;
use App\Policies\SuperOrganizationPolicy;
use App\Repositories\Contracts\{FranchiseRepositoryInterface, OrganizationRepositoryInterface, UserRepositoryInterface};
use App\Domain\Franchises\FranchiseDefaultsService;
use App\Domain\Audit\AuditService;
use App\Core\Exceptions\{NotFoundException, ConflictException, ValidationException};

final class FranchisesController
{
    public function __construct(
        private FranchiseRepositoryInterface $repo,
        private OrganizationRepositoryInterface $orgRepo,
        private UserRepositoryInterface $userRepo,
        private FranchiseDefaultsService $defaults,
        private AuditService $audit,
        private PasswordHasher $hasher,
    ) {}

    private function authorize(): TenantContext
    {
        /** @var TenantContext $ctx */
        $ctx = Container::getInstance()->make(TenantContext::class);
        $policy = new SuperOrganizationPolicy($ctx);
        $policy->authorize('manage');
        return $ctx;
    }

    public function index(Request $r): Response
    {
        $this->authorize();
        $page    = (int) $r->query('page', '1');
        $perPage = (int) $r->query('per_page', '25');
        $filters = [
            'org_ref' => $r->query('org_ref', ''),
            'status'  => $r->query('status', ''),
            'search'  => $r->query('search', ''),
        ];

        $res = $this->repo->list($filters, $page, $perPage);
        return Response::json(200, $res['data'], $res['meta']);
    }

    public function show(Request $r): Response
    {
        $this->authorize();
        $ref = $r->param('ref');
        $frn = $this->repo->findByRef($ref);
        if (!$frn) {
            throw new NotFoundException('FRANCHISE_NOT_FOUND', "Franchise {$ref} not found.");
        }
        return Response::json(200, $frn);
    }

    public function create(Request $r): Response
    {
        $ctx = $this->authorize();
        $clean = Validation::validate($r->all(), [
            'org_ref'        => 'required|string',
            'franchise_code' => 'required|string',
            'franchise_name' => 'required|string',
        ]);

        $org = $this->orgRepo->findByRef($clean['org_ref']);
        if (!$org) {
            throw new NotFoundException('ORGANIZATION_NOT_FOUND', "Organization {$clean['org_ref']} not found.");
        }

        $code = strtoupper(trim($clean['franchise_code']));
        if ($this->repo->findByCode($clean['org_ref'], $code)) {
            throw new ConflictException('FRANCHISE_CODE_EXISTS', "Franchise code {$code} is already taken in this organization.");
        }

        $frnRef = RefGenerator::make('FRN');
        $data = [
            'franchise_ref'     => $frnRef,
            'org_ref'           => $clean['org_ref'],
            'franchise_code'    => $code,
            'franchise_name'    => trim($clean['franchise_name']),
            'gstin'             => $r->input('gstin'),
            'drug_license_no'   => $r->input('drug_license_no'),
            'address'           => $r->input('address'),
            'brand_primary_hex' => $r->input('brand_primary_hex'),
            'brand_accent_hex'  => $r->input('brand_accent_hex'),
            'status'            => 'ACTIVE',
            'created_by_ref'    => $ctx->userRef,
            'created_at'        => date('Y-m-d H:i:s'),
        ];

        $this->repo->create($data);

        // Auto-seed defaults for the new franchise (P1-S11)
        $this->defaults->seed($clean['org_ref'], $frnRef, $ctx->userRef);

        $this->audit->log(
            ctx: $ctx,
            category: 'BUSINESS',
            action: 'franchise.created',
            entityType: 'franchise',
            entityRef: $frnRef,
            after: $data
        );

        return Response::json(201, $data);
    }

    public function update(Request $r): Response
    {
        $ctx = $this->authorize();
        $ref = $r->param('ref');
        $frn = $this->repo->findByRef($ref);
        if (!$frn) {
            throw new NotFoundException('FRANCHISE_NOT_FOUND', "Franchise {$ref} not found.");
        }

        $updates = [];
        if ($r->has('franchise_name'))    $updates['franchise_name'] = trim((string)$r->input('franchise_name'));
        if ($r->has('gstin'))             $updates['gstin'] = $r->input('gstin');
        if ($r->has('drug_license_no'))   $updates['drug_license_no'] = $r->input('drug_license_no');
        if ($r->has('address'))           $updates['address'] = $r->input('address');
        if ($r->has('brand_primary_hex')) $updates['brand_primary_hex'] = $r->input('brand_primary_hex');
        if ($r->has('brand_accent_hex'))  $updates['brand_accent_hex'] = $r->input('brand_accent_hex');

        if (!empty($updates)) {
            $this->repo->update($ref, $updates);
            $this->audit->log(
                ctx: $ctx,
                category: 'BUSINESS',
                action: 'franchise.updated',
                entityType: 'franchise',
                entityRef: $ref,
                before: $frn,
                after: array_merge($frn, $updates)
            );
        }

        return Response::json(200, $this->repo->findByRef($ref));
    }

    public function suspend(Request $r): Response
    {
        $ctx = $this->authorize();
        $ref = $r->param('ref');
        $frn = $this->repo->findByRef($ref);
        if (!$frn) {
            throw new NotFoundException('FRANCHISE_NOT_FOUND', "Franchise {$ref} not found.");
        }

        $this->repo->setStatus($ref, 'SUSPENDED');
        $this->audit->log(
            ctx: $ctx,
            category: 'BUSINESS',
            action: 'franchise.suspended',
            entityType: 'franchise',
            entityRef: $ref
        );

        return Response::json(200, ['status' => 'SUSPENDED']);
    }

    public function activate(Request $r): Response
    {
        $ctx = $this->authorize();
        $ref = $r->param('ref');
        $frn = $this->repo->findByRef($ref);
        if (!$frn) {
            throw new NotFoundException('FRANCHISE_NOT_FOUND', "Franchise {$ref} not found.");
        }

        $this->repo->setStatus($ref, 'ACTIVE');
        $this->audit->log(
            ctx: $ctx,
            category: 'BUSINESS',
            action: 'franchise.activated',
            entityType: 'franchise',
            entityRef: $ref
        );

        return Response::json(200, ['status' => 'ACTIVE']);
    }

    /**
     * POST /super/franchises/{ref}/admins
     * Create first FRANCHISE_ADMIN for franchise (must_change_password=1)
     */
    public function createAdmin(Request $r): Response
    {
        $ctx = $this->authorize();
        $ref = $r->param('ref');
        $frn = $this->repo->findByRef($ref);
        if (!$frn) {
            throw new NotFoundException('FRANCHISE_NOT_FOUND', "Franchise {$ref} not found.");
        }

        $clean = Validation::validate($r->all(), [
            'full_name' => 'required|string',
            'email'     => 'required|email',
            'password'  => 'required|string',
        ]);

        $email = strtolower(trim($clean['email']));
        if ($this->userRepo->findByEmailAndTenant($email, $ref)) {
            throw new ConflictException('USER_EMAIL_EXISTS', "User with email {$email} already exists in this franchise.");
        }

        $userRef = RefGenerator::make('USR');
        $hash = $this->hasher->hash($clean['password']);

        $user = [
            'user_ref'             => $userRef,
            'org_ref'              => $frn['org_ref'],
            'franchise_ref'        => $ref,
            'role'                 => 'FRANCHISE_ADMIN',
            'full_name'            => trim($clean['full_name']),
            'email'                => $email,
            'mobile'               => $r->input('mobile'),
            'password_hash'        => $hash,
            'must_change_password' => 1,
            'status'               => 'ACTIVE',
            'created_by_ref'       => $ctx->userRef,
            'created_at'           => date('Y-m-d H:i:s'),
        ];

        $this->userRepo->create($user);

        $this->audit->log(
            ctx: $ctx,
            category: 'BUSINESS',
            action: 'user.created_franchise_admin',
            entityType: 'user',
            entityRef: $userRef,
            after: $user
        );

        unset($user['password_hash']);
        return Response::json(201, $user);
    }
}
