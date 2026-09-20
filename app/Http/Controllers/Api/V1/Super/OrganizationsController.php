<?php
declare(strict_types=1);
namespace App\Http\Controllers\Api\V1\Super;

use App\Core\{Request, Response, Container, Validation, TenantContext, RefGenerator};
use App\Policies\SuperOrganizationPolicy;
use App\Repositories\Contracts\OrganizationRepositoryInterface;
use App\Domain\Audit\AuditService;
use App\Core\Exceptions\{NotFoundException, ConflictException, ForbiddenException};

final class OrganizationsController
{
    public function __construct(
        private OrganizationRepositoryInterface $repo,
        private AuditService $audit,
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
            'status' => $r->query('status', ''),
            'search' => $r->query('search', ''),
        ];

        $res = $this->repo->list($filters, $page, $perPage);
        return Response::json(200, $res['data'], $res['meta']);
    }

    public function show(Request $r): Response
    {
        $this->authorize();
        $ref = $r->param('ref');
        $org = $this->repo->findByRef($ref);
        if (!$org) {
            throw new NotFoundException('ORGANIZATION_NOT_FOUND', "Organization {$ref} not found.");
        }
        return Response::json(200, $org);
    }

    public function create(Request $r): Response
    {
        $ctx = $this->authorize();
        $clean = Validation::validate($r->all(), [
            'org_code' => 'required|string',
            'org_name' => 'required|string',
        ]);

        $code = strtoupper(trim($clean['org_code']));
        if ($this->repo->findByCode($code)) {
            throw new ConflictException('ORGANIZATION_CODE_EXISTS', "Organization code {$code} is already taken.");
        }

        $orgRef = RefGenerator::make('ORG');
        $data = [
            'org_ref'           => $orgRef,
            'org_code'          => $code,
            'org_name'          => trim($clean['org_name']),
            'status'            => 'ACTIVE',
            'brand_primary_hex' => $r->input('brand_primary_hex'),
            'brand_accent_hex'  => $r->input('brand_accent_hex'),
            'created_by_ref'    => $ctx->userRef,
            'created_at'        => date('Y-m-d H:i:s'),
        ];

        $this->repo->create($data);
        $this->audit->log(
            ctx: $ctx,
            category: 'BUSINESS',
            action: 'organization.created',
            entityType: 'organization',
            entityRef: $orgRef,
            after: $data
        );

        return Response::json(201, $data);
    }

    public function update(Request $r): Response
    {
        $ctx = $this->authorize();
        $ref = $r->param('ref');
        $org = $this->repo->findByRef($ref);
        if (!$org) {
            throw new NotFoundException('ORGANIZATION_NOT_FOUND', "Organization {$ref} not found.");
        }

        $updates = [];
        if ($r->has('org_name')) $updates['org_name'] = trim((string)$r->input('org_name'));
        if ($r->has('brand_primary_hex')) $updates['brand_primary_hex'] = $r->input('brand_primary_hex');
        if ($r->has('brand_accent_hex')) $updates['brand_accent_hex'] = $r->input('brand_accent_hex');

        if (!empty($updates)) {
            $this->repo->update($ref, $updates);
            $this->audit->log(
                ctx: $ctx,
                category: 'BUSINESS',
                action: 'organization.updated',
                entityType: 'organization',
                entityRef: $ref,
                before: $org,
                after: array_merge($org, $updates)
            );
        }

        return Response::json(200, $this->repo->findByRef($ref));
    }
}
