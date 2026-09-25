<?php
declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Core\{QueryParams, Request, Response, TenantContext, Validation};
use App\Core\Exceptions\NotFoundException;
use App\Domain\Audit\AuditService;
use App\Domain\Authorization\AuthorizationService;
use App\Domain\Dispatch\DispatchService;
use App\Repositories\Contracts\{DispatchRepositoryInterface, PartyRepositoryInterface};

final class DispatchesController
{
    public function __construct(private DispatchRepositoryInterface $dispatches, private PartyRepositoryInterface $parties, private DispatchService $service, private AuthorizationService $authorization, private AuditService $audit) {}

    public function index(Request $r): Response
    {
        $ctx = TenantContext::get(); $this->authorization->requirePermission($ctx, 'dispatch', 'view'); $q = QueryParams::fromRequest($r, ['created_at','dispatch_date','status']);
        if ($ctx->scopeFor('dispatch') === 'NONE') return Response::json(200, [], ['page' => $q['page'], 'per_page' => $q['per_page'], 'total' => 0, 'total_pages' => 0]);
        $res = $this->dispatches->list($ctx->requireFranchise(), ['status' => $q['status'], 'search' => $q['search']], $q['page'], $q['per_page']);
        $items = array_values(array_filter($res['data'], fn(array $d) => $this->visible($ctx, $d)));
        return Response::json(200, $items, ['page' => $q['page'], 'per_page' => $q['per_page'], 'total' => count($items), 'total_pages' => (int)ceil(count($items) / $q['per_page'])]);
    }

    public function show(Request $r): Response
    {
        $ctx = TenantContext::get(); $this->authorization->requirePermission($ctx, 'dispatch', 'view'); $ref = (string)$r->param('ref'); $dispatch = $this->dispatches->findByRef($ctx->requireFranchise(), $ref);
        if (!$dispatch || !$this->visible($ctx, $dispatch)) throw new NotFoundException('DISPATCH_NOT_FOUND', 'Dispatch not found.');
        $dispatch['history'] = $this->dispatches->history($ctx->requireFranchise(), $ref); return Response::json(200, $dispatch);
    }

    public function store(Request $r): Response
    {
        $ctx = TenantContext::get(); $this->authorization->requirePermission($ctx, 'dispatch', 'create');
        $data = Validation::validate($r->all(), ['invoice_ref' => 'required|string', 'lr_number' => 'required|string|min:1', 'boxes' => 'required|integer|min:1']);
        $result = $this->service->create($ctx->orgRef, $ctx->requireFranchise(), $data['invoice_ref'], $r->input('transporter_ref'), $data['lr_number'], $r->input('tracking_url'), (int)$data['boxes'], $r->input('remarks'), $ctx->userRef);
        $this->audit->log(ctx: $ctx, category: 'DISPATCH', action: 'dispatch.created', entityType: 'dispatch', entityRef: $result['dispatch_ref'], after: $result); return Response::json(201, $result);
    }

    public function deliver(Request $r): Response
    {
        $ctx = TenantContext::get(); $this->authorization->requirePermission($ctx, 'dispatch', 'confirm'); $ref = (string)$r->param('ref'); $dispatch = $this->dispatches->findByRef($ctx->requireFranchise(), $ref);
        if (!$dispatch || !$this->visible($ctx, $dispatch)) throw new NotFoundException('DISPATCH_NOT_FOUND', 'Dispatch not found.');
        $result = $this->service->deliver($ctx->orgRef, $ctx->requireFranchise(), $ref, $ctx->userRef, $r->input('delivery_remarks'));
        $this->audit->log(ctx: $ctx, category: 'DISPATCH', action: 'dispatch.delivered', entityType: 'dispatch', entityRef: $ref, before: $dispatch, after: $result, reason: $r->input('delivery_remarks')); return Response::json(200, $result);
    }

    private function visible(TenantContext $ctx, array $dispatch): bool
    {
        if ($ctx->isDistributor() && ($dispatch['party_ref'] ?? null) !== $ctx->partyRef) return false;
        try { $territory = $this->parties->findTerritoryRefs($ctx->requireFranchise(), (string)$dispatch['party_ref'])[0] ?? null; $this->authorization->requireRecordScope($ctx, 'dispatch', $dispatch['sales_user_ref'] ?? null, $territory, $dispatch['franchise_ref'] ?? null); return true; } catch (NotFoundException) { return false; }
    }
}
