<?php
declare(strict_types=1);
namespace App\Http\Controllers\Api\V1\Admin;

use App\Core\{QueryParams, Request, Response, TenantContext, Validation};
use App\Core\Exceptions\{NotFoundException, ValidationException};
use App\Domain\Audit\AuditService;
use App\Domain\Authorization\AuthorizationService;
use App\Domain\Territory\{TerritoryResolver, TerritoryService, TerritoryValidator};
use App\Repositories\Contracts\{PartyRepositoryInterface, PartyTerritoryRepositoryInterface};

final class TerritoriesController
{
    public function __construct(
        private PartyTerritoryRepositoryInterface $territories,
        private PartyRepositoryInterface $parties,
        private TerritoryService $territoryService,
        private TerritoryValidator $validator,
        private TerritoryResolver $resolver,
        private AuthorizationService $authorization,
        private AuditService $audit,
        private \PDO $pdo,
    ) {}

    public function index(Request $r): Response
    {
        $ctx = TenantContext::get(); $f = $ctx->requireFranchise(); $this->authorization->requirePermission($ctx, 'territory', 'view'); $q = QueryParams::fromRequest($r, ['effective_from','created_at']);
        $filters = ['search' => $q['search'], 'status' => $q['status'], 'party_ref' => $r->query('party_ref'), 'level' => $r->query('level'), 'pincode' => $r->query('pincode'), 'district_ref' => $r->query('district_ref')];
        $scope = $ctx->scopeFor('territory'); if ($scope === 'NONE') return Response::json(200, [], ['page' => $q['page'], 'per_page' => $q['per_page'], 'total' => 0, 'total_pages' => 0]);
        if ($scope === 'OWN') $filters['party_ref'] = $ctx->partyRef;
        if ($scope === 'TERRITORY') { if (!$ctx->territoryRefs) return Response::json(200, [], ['page' => $q['page'], 'per_page' => $q['per_page'], 'total' => 0, 'total_pages' => 0]); $filters['territory_refs'] = $ctx->territoryRefs; }
        $res = $this->territories->list($f, $filters, $q['page'], $q['per_page']); return Response::json(200, $res['items'], ['page' => $res['page'], 'per_page' => $res['per_page'], 'total' => $res['total'], 'total_pages' => $res['total_pages']]);
    }

    public function show(Request $r): Response
    {
        $ctx = TenantContext::get(); $f = $ctx->requireFranchise(); $this->authorization->requirePermission($ctx, 'territory', 'view'); $ref = (string)$r->param('ref'); $row = $this->territories->findByRef($f, $ref); if (!$row) throw new NotFoundException('TERRITORY_NOT_FOUND', 'Territory allocation not found.');
        $this->checkScope($ctx, $row); return Response::json(200, $row);
    }

    public function store(Request $r): Response
    {
        $ctx = TenantContext::get(); $f = $ctx->requireFranchise(); $this->authorization->requirePermission($ctx, 'territory', 'allocate'); $clean = $this->validateMapping($r, $ctx);
        if ($ctx->scopeFor('territory') === 'OWN') $clean['party_ref'] = $ctx->partyRef; $clean['org_ref'] = $ctx->orgRef; $clean['franchise_ref'] = $f; $clean['created_by_ref'] = $ctx->userRef; $clean['status'] = 'ACTIVE';
        $ref = $this->territoryService->create($clean); $after = $this->territories->findByRef($f, $ref); $this->audit->log(ctx: $ctx, category: 'BUSINESS', action: 'territory.created', entityType: 'party_territory', entityRef: $ref, after: $after ?? $clean); return Response::json(201, $after);
    }

    public function update(Request $r): Response
    {
        $ctx = TenantContext::get(); $f = $ctx->requireFranchise(); $this->authorization->requirePermission($ctx, 'territory', 'edit'); $ref = (string)$r->param('ref'); $before = $this->territories->findByRef($f, $ref); if (!$before) throw new NotFoundException('TERRITORY_NOT_FOUND', 'Territory allocation not found.'); $this->checkScope($ctx, $before);
        $clean = Validation::validate($r->all(), ['effective_from' => 'required|date:Y-m-d', 'effective_to' => 'date:Y-m-d', 'is_exclusive' => 'bool']); $this->territoryService->update($f, $ref, array_merge($clean, ['updated_by_ref' => $ctx->userRef])); $after = $this->territories->findByRef($f, $ref); $this->audit->log(ctx: $ctx, category: 'BUSINESS', action: 'territory.updated', entityType: 'party_territory', entityRef: $ref, before: $before, after: $after ?? $clean); return Response::json(200, $after);
    }

    public function status(Request $r): Response
    {
        $ctx = TenantContext::get(); $f = $ctx->requireFranchise(); $this->authorization->requirePermission($ctx, 'territory', 'edit'); $ref = (string)$r->param('ref'); $before = $this->territories->findByRef($f, $ref); if (!$before) throw new NotFoundException('TERRITORY_NOT_FOUND', 'Territory allocation not found.'); $this->checkScope($ctx, $before); $status = Validation::validate($r->all(), ['status' => 'required|enum:ACTIVE,INACTIVE'])['status']; $this->territories->update($f, $ref, ['status' => $status]); $this->audit->log(ctx: $ctx, category: 'BUSINESS', action: 'territory.status_changed', entityType: 'party_territory', entityRef: $ref, before: $before, after: ['status' => $status]); return Response::json(200, ['territory_ref' => $ref, 'status' => $status]);
    }

    public function resolve(Request $r): Response
    {
        $ctx = TenantContext::get(); $f = $ctx->requireFranchise(); $this->authorization->requirePermission($ctx, 'territory', 'view'); $clean = Validation::validate($r->all(), ['party_ref' => 'required|string', 'pincode' => 'required|pincode', 'date' => 'date:Y-m-d']); $party = $this->parties->findByRef($f, $clean['party_ref']); if (!$party) throw new NotFoundException('PARTY_NOT_FOUND', 'Party not found.'); $this->checkScope($ctx, ['party_ref' => $clean['party_ref']]); return Response::json(200, $this->resolver->resolve($f, $clean['party_ref'], $clean['pincode'], $r->input('district_ref'), $clean['date'] ?? null));
    }

    /** Legacy compatibility endpoint; it retains existing Order-facing semantics and is not an Order workflow implementation here. */
    public function validate(Request $r): Response
    {
        $ctx = TenantContext::get(); $this->authorization->requirePermission($ctx, 'territory', 'view'); $clean = Validation::validate($r->all(), ['party_ref' => 'required|string', 'pincode' => 'required|pincode']); return Response::json(200, $this->validator->validate($ctx->requireFranchise(), $clean['party_ref'], $clean['pincode'], $r->input('date')));
    }

    public function override(Request $r): Response
    {
        $ctx = TenantContext::get(); $this->authorization->requirePermission($ctx, 'territory', 'override');
        // Existing persistence endpoint is retained only as an audited admin foundation; Order flow remains deferred.
        $clean = Validation::validate($r->all(), ['order_ref' => 'required|string', 'party_ref' => 'required|string', 'pincode' => 'required|pincode', 'reason' => 'required|string|min:2']);
        if (!$this->parties->findByRef($ctx->requireFranchise(), $clean['party_ref'])) throw new NotFoundException('PARTY_NOT_FOUND', 'Party not found.');
        $data = array_merge($clean, ['org_ref' => $ctx->orgRef, 'franchise_ref' => $ctx->requireFranchise(), 'approved_by_ref' => $ctx->userRef]);
        $ref = $this->territoryService->createOverride($data); $this->audit->log(ctx: $ctx, category: 'BUSINESS', action: 'territory.override_recorded', entityType: 'territory_override', entityRef: $ref, after: $data); return Response::json(201, ['override_ref' => $ref, 'status' => 'RECORDED']);
    }

    private function validateMapping(Request $r, TenantContext $ctx): array
    {
        $clean = Validation::validate($r->all(), ['party_ref' => 'required|string', 'level' => 'required|enum:PINCODE,DISTRICT', 'pincode' => 'pincode', 'district_ref' => 'string', 'effective_from' => 'required|date:Y-m-d', 'effective_to' => 'date:Y-m-d', 'is_exclusive' => 'bool']);
        $party = $this->parties->findByRef($ctx->requireFranchise(), $clean['party_ref']); if (!$party || ($party['status'] ?? '') !== 'ACTIVE') throw new ValidationException('INVALID_PARTY', 'party_ref must reference an active party.');
        if ($clean['level'] === 'PINCODE') { if (empty($clean['pincode'])) throw new ValidationException('PINCODE_REQUIRED', 'pincode is required for PINCODE mapping.'); $stmt = $this->pdo->prepare('SELECT district_ref FROM pincodes WHERE pincode = ? LIMIT 1'); $stmt->execute([$clean['pincode']]); $district = $stmt->fetchColumn(); if (!$district) throw new ValidationException('INVALID_PINCODE', 'pincode not found in geography master.'); $clean['district_ref'] = $clean['district_ref'] ?? $district; }
        if ($clean['level'] === 'DISTRICT' && empty($clean['district_ref'])) throw new ValidationException('DISTRICT_REQUIRED', 'district_ref is required for DISTRICT mapping.');
        if (($clean['effective_to'] ?? null) !== null && $clean['effective_to'] < $clean['effective_from']) throw new ValidationException('INVALID_EFFECTIVE_DATES', 'effective_to must be on or after effective_from.');
        return $clean;
    }

    private function checkScope(TenantContext $ctx, array $row): void
    {
        $party = $this->parties->findByRef($ctx->requireFranchise(), (string)($row['party_ref'] ?? '')); if (!$party) throw new NotFoundException('RECORD_NOT_FOUND', 'Record not found.');
        $this->authorization->requireRecordScope($ctx, 'territory', $party['sales_user_ref'] ?? null, (string)($row['territory_ref'] ?? ''), $ctx->franchiseRef);
    }
}
