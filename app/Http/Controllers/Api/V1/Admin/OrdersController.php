<?php
declare(strict_types=1);
namespace App\Http\Controllers\Api\V1\Admin;

use App\Core\{QueryParams, Request, Response, TenantContext, Validation};
use App\Core\Exceptions\{ConflictException, NotFoundException};
use App\Domain\Audit\AuditService;
use App\Domain\Authorization\AuthorizationService;
use App\Domain\Orders\OrderService;
use App\Repositories\Contracts\{OrderRepositoryInterface, PartyRepositoryInterface};

final class OrdersController
{
    public function __construct(private OrderRepositoryInterface $orderRepo, private PartyRepositoryInterface $parties, private OrderService $orderService, private AuthorizationService $authorization, private AuditService $audit) {}

    public function index(Request $r): Response
    {
        $ctx = TenantContext::get(); $f = $ctx->requireFranchise(); $this->authorization->requirePermission($ctx, 'orders', 'view'); $q = QueryParams::fromRequest($r, ['created_at','order_date','grand_total','status']); $scope = $ctx->scopeFor('orders');
        if ($scope === 'NONE') return Response::json(200, [], ['page' => $q['page'], 'per_page' => $q['per_page'], 'total' => 0, 'total_pages' => 0]);
        $filters = ['status' => $q['status'], 'search' => $q['search'], 'sort_by' => $q['sort_by'], 'sort_dir' => $q['sort_dir']]; $partyRef = $ctx->isDistributor() ? $ctx->partyRef : null;
        if ($scope === 'OWN') $filters['sales_user_ref'] = $ctx->userRef;
        if ($scope === 'TEAM') $filters['sales_user_refs'] = array_values(array_unique(array_merge([$ctx->userRef], $ctx->teamUserRefs)));
        if ($scope === 'TERRITORY') { if (!$ctx->territoryRefs) return Response::json(200, [], ['page' => $q['page'], 'per_page' => $q['per_page'], 'total' => 0, 'total_pages' => 0]); $filters['territory_refs'] = $ctx->territoryRefs; }
        $res = $this->orderRepo->list($f, $filters, $q['page'], $q['per_page'], $partyRef); return Response::json(200, $res['data'], $res['meta']);
    }

    public function show(Request $r): Response
    {
        $ctx = TenantContext::get(); $f = $ctx->requireFranchise(); $this->authorization->requirePermission($ctx, 'orders', 'view'); $ref = (string)$r->param('ref'); $order = $this->orderRepo->findByRef($f, $ref); if (!$order) throw new NotFoundException('ORDER_NOT_FOUND', 'Order not found.'); $this->checkScope($ctx, $order);
        $order['items'] = $this->orderRepo->getItems($f, $ref); $order['history'] = $this->orderRepo->getHistory($f, $ref); return Response::json(200, $order);
    }

    public function store(Request $r): Response
    {
        $ctx = TenantContext::get(); $this->authorization->requirePermission($ctx, 'orders', 'create'); $clean = Validation::validate($r->all(), ['party_ref' => 'required|string', 'client_order_ref' => 'required|string|min:1', 'channel' => 'required|enum:PORTAL,SALES,ADMIN', 'items' => 'required|array|min:1']);
        $sales = $ctx->scopeFor('orders') === 'ALL' ? ($r->input('sales_user_ref') ?: ($ctx->isSales() ? $ctx->userRef : null)) : $ctx->userRef;
        $res = $this->orderService->createOrder($ctx->orgRef, $ctx->requireFranchise(), $clean['party_ref'], $clean['client_order_ref'], $clean['channel'], $clean['items'], $sales, $r->input('shipping_address'), $r->input('shipping_pincode'), $r->input('remarks'), $ctx->userRef);
        $this->audit->log(ctx: $ctx, category: 'BUSINESS', action: 'order.draft_created', entityType: 'order', entityRef: $res['order_ref'], after: $res); return Response::json(201, $res);
    }

    public function updateDraft(Request $r): Response
    {
        $ctx = TenantContext::get(); $f = $ctx->requireFranchise(); $this->authorization->requirePermission($ctx, 'orders', 'editDraft'); $ref = (string)$r->param('ref'); $order = $this->orderRepo->findByRef($f, $ref); if (!$order) throw new NotFoundException('ORDER_NOT_FOUND', 'Order not found.'); $this->checkScope($ctx, $order);
        $clean = Validation::validate($r->all(), ['party_ref' => 'required|string', 'items' => 'required|array|min:1']); $res = $this->orderService->updateDraft($ctx->orgRef, $f, $ref, $clean['party_ref'], $clean['items'], $r->input('shipping_address'), $r->input('shipping_pincode'), $r->input('remarks'), $ctx->userRef); $this->audit->log(ctx: $ctx, category: 'BUSINESS', action: 'order.draft_updated', entityType: 'order', entityRef: $ref, after: $res); return Response::json(200, $res);
    }

    public function deleteDraft(Request $r): Response
    {
        $ctx = TenantContext::get(); $f = $ctx->requireFranchise(); $this->authorization->requirePermission($ctx, 'orders', 'deleteDraft'); $ref = (string)$r->param('ref'); $order = $this->orderRepo->findByRef($f, $ref); if (!$order) throw new NotFoundException('ORDER_NOT_FOUND', 'Order not found.'); $this->checkScope($ctx, $order); $this->orderService->deleteDraft($f, $ref); $this->audit->log(ctx: $ctx, category: 'BUSINESS', action: 'order.draft_deleted', entityType: 'order', entityRef: $ref, before: $order); return Response::json(200, ['order_ref' => $ref, 'status' => 'DELETED']);
    }

    public function submit(Request $r): Response
    {
        $ctx = TenantContext::get(); $f = $ctx->requireFranchise(); $this->authorization->requirePermission($ctx, 'orders', 'submit'); $ref = (string)$r->param('ref'); $order = $this->orderRepo->findByRef($f, $ref); if (!$order) throw new NotFoundException('ORDER_NOT_FOUND', 'Order not found.'); $this->checkScope($ctx, $order); $res = $this->orderService->submitOrder($ctx->orgRef, $f, $ref, $ctx->userRef); $this->audit->log(ctx: $ctx, category: 'BUSINESS', action: 'order.submitted', entityType: 'order', entityRef: $ref, after: $res); return Response::json(200, $res);
    }

    public function confirm(Request $r): Response
    {
        $ctx = TenantContext::get(); $f = $ctx->requireFranchise(); $this->authorization->requirePermission($ctx, 'orders', 'confirm'); $ref = (string)$r->param('ref'); $order = $this->orderRepo->findByRef($f, $ref); if (!$order) throw new NotFoundException('ORDER_NOT_FOUND', 'Order not found.'); $this->checkScope($ctx, $order); $res = $this->orderService->confirmOrder($ctx->orgRef, $f, $ref, $ctx->userRef); $this->audit->log(ctx: $ctx, category: 'BUSINESS', action: 'order.confirmed', entityType: 'order', entityRef: $ref, after: $res); return Response::json(200, $res);
    }

    public function cancel(Request $r): Response
    {
        $ctx = TenantContext::get(); $f = $ctx->requireFranchise(); $this->authorization->requirePermission($ctx, 'orders', 'cancel'); $ref = (string)$r->param('ref'); $order = $this->orderRepo->findByRef($f, $ref); if (!$order) throw new NotFoundException('ORDER_NOT_FOUND', 'Order not found.'); $this->checkScope($ctx, $order); $reason = Validation::validate($r->all(), ['reason' => 'required|string|min:2'])['reason']; $this->orderService->cancelOrder($ctx->orgRef, $f, $ref, $ctx->userRef, $reason); $this->audit->log(ctx: $ctx, category: 'BUSINESS', action: 'order.cancelled', entityType: 'order', entityRef: $ref, before: $order, after: ['status' => 'CANCELLED', 'reason' => $reason]); return Response::json(200, ['order_ref' => $ref, 'status' => 'CANCELLED']);
    }

    private function checkScope(TenantContext $ctx, array $order): void
    {
        if ($ctx->isDistributor() && ($order['party_ref'] ?? null) !== $ctx->partyRef) throw new NotFoundException('ORDER_NOT_FOUND', 'Order not found.');
        $territory = $this->parties->findTerritoryRefs($ctx->requireFranchise(), (string)$order['party_ref'])[0] ?? null;
        $this->authorization->requireRecordScope($ctx, 'orders', $order['sales_user_ref'] ?? null, $territory, $ctx->franchiseRef);
    }
}
