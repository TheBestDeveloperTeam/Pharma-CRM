<?php
declare(strict_types=1);
namespace App\Http\Controllers\Api\V1\Admin;

use App\Core\{QueryParams, Request, Response, TenantContext, Validation};
use App\Core\Exceptions\NotFoundException;
use App\Domain\Audit\AuditService;
use App\Domain\Authorization\AuthorizationService;
use App\Domain\Inventory\{FefoAllocator, InventoryService};
use App\Repositories\Contracts\{InventoryBatchRepositoryInterface, InventoryMovementRepositoryInterface, StockReservationRepositoryInterface};

final class InventoryController
{
    public function __construct(private InventoryService $inventoryService, private FefoAllocator $fefo, private InventoryBatchRepositoryInterface $batchRepo, private InventoryMovementRepositoryInterface $movementRepo, private StockReservationRepositoryInterface $reservations, private AuthorizationService $authorization, private AuditService $audit) {}

    public function index(Request $r): Response
    {
        $ctx = TenantContext::get(); $this->authorization->requirePermission($ctx, 'inventory', 'view'); $q = QueryParams::fromRequest($r, ['expiry_date','created_at']); $res = $this->batchRepo->list($ctx->requireFranchise(), ['search' => $q['search'], 'status' => $q['status'], 'product_ref' => $r->query('product_ref')], $q['page'], $q['per_page']); return Response::json(200, $res['items'], ['page' => $res['page'], 'per_page' => $res['per_page'], 'total' => $res['total'], 'total_pages' => $res['total_pages']]);
    }

    public function showBatch(Request $r): Response
    {
        $ctx = TenantContext::get(); $this->authorization->requirePermission($ctx, 'inventory', 'view'); $f = $ctx->requireFranchise(); $ref = (string)$r->param('ref'); $batch = $this->batchRepo->findByRef($f, $ref); if (!$batch) throw new NotFoundException('BATCH_NOT_FOUND', 'Inventory batch not found.'); $batch['movements'] = $this->movementRepo->listByBatch($f, $ref); return Response::json(200, $batch);
    }

    public function receive(Request $r): Response
    {
        $ctx = TenantContext::get(); $this->authorization->requirePermission($ctx, 'inventory', 'create'); $clean = Validation::validate($r->all(), ['product_ref' => 'required|string', 'batch_no' => 'required|string', 'expiry_date' => 'required|date:Y-m-d', 'qty' => 'required|integer|min:1', 'manufacturing_date' => 'date:Y-m-d']); $ref = $this->inventoryService->receiveGoods($ctx->orgRef, $ctx->requireFranchise(), $clean['product_ref'], $clean['batch_no'], $clean['expiry_date'], (int)$clean['qty'], $clean['manufacturing_date'] ?? null, $r->input('location_code'), $ctx->userRef); $after = $this->batchRepo->findByRef($ctx->requireFranchise(), $ref); $this->audit->log(ctx: $ctx, category: 'INVENTORY', action: 'batch.received', entityType: 'inventory_batch', entityRef: $ref, after: $after); return Response::json(201, $after);
    }

    public function adjust(Request $r): Response
    {
        $ctx = TenantContext::get(); $this->authorization->requirePermission($ctx, 'inventory', 'adjust'); $f = $ctx->requireFranchise(); $ref = (string)$r->param('ref'); $clean = Validation::validate($r->all(), ['delta_qty' => 'required|integer', 'reason' => 'required|string|min:2']); $this->inventoryService->adjustStock($ctx->orgRef, $f, $ref, (int)$clean['delta_qty'], $clean['reason'], $ctx->userRef); $after = $this->batchRepo->findByRef($f, $ref); $this->audit->log(ctx: $ctx, category: 'INVENTORY', action: 'batch.adjusted', entityType: 'inventory_batch', entityRef: $ref, after: $after, reason: $clean['reason']); return Response::json(200, $after);
    }

    public function nearExpiry(Request $r): Response
    {
        $ctx = TenantContext::get(); $this->authorization->requirePermission($ctx, 'inventory', 'view'); $days = max(0, min(3650, (int)$r->query('days', 90))); return Response::json(200, $this->inventoryService->listNearExpiry($ctx->requireFranchise(), $days));
    }

    public function reservations(Request $r): Response
    {
        $ctx = TenantContext::get(); $this->authorization->requirePermission($ctx, 'inventory', 'view'); return Response::json(200, $this->reservations->listForOrder($ctx->requireFranchise(), (string)$r->param('order_ref')));
    }

    public function release(Request $r): Response
    {
        $ctx = TenantContext::get(); $this->authorization->requirePermission($ctx, 'inventory', 'release'); $orderRef = (string)$r->param('order_ref'); $this->fefo->releaseOrderStock($ctx->orgRef, $ctx->requireFranchise(), $orderRef, $ctx->userRef); $this->audit->log(ctx: $ctx, category: 'INVENTORY', action: 'reservation.released', entityType: 'order', entityRef: $orderRef); return Response::json(200, ['order_ref' => $orderRef, 'status' => 'RELEASED']);
    }

    public function consume(Request $r): Response
    {
        $ctx = TenantContext::get(); $this->authorization->requirePermission($ctx, 'inventory', 'reserve'); $orderRef = (string)$r->param('order_ref'); $this->fefo->consumeOrderStock($ctx->orgRef, $ctx->requireFranchise(), $orderRef, $ctx->userRef); $this->audit->log(ctx: $ctx, category: 'INVENTORY', action: 'reservation.consumed', entityType: 'order', entityRef: $orderRef); return Response::json(200, ['order_ref' => $orderRef, 'status' => 'CONSUMED']);
    }
}
