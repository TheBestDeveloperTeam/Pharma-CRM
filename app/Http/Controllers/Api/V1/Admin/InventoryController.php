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
        $ctx = TenantContext::get(); 
        $this->authorization->requirePermission($ctx, 'inventory', 'create'); 
        $clean = Validation::validate($r->all(), ['product_ref' => 'required|string', 'batch_no' => 'required|string', 'expiry_date' => 'required|date:Y-m-d', 'qty' => 'required|integer|min:1', 'manufacturing_date' => 'date:Y-m-d']); 
        
        $tx = new \App\Core\Transaction(\App\Core\Database::connection());
        $after = $tx->run(function() use ($ctx, $clean, $r) {
            $ref = $this->inventoryService->receiveGoods($ctx->orgRef, $ctx->requireFranchise(), $clean['product_ref'], $clean['batch_no'], $clean['expiry_date'], (int)$clean['qty'], $clean['manufacturing_date'] ?? null, $r->input('location_code'), $ctx->userRef); 
            $after = $this->batchRepo->findByRef($ctx->requireFranchise(), $ref); 
            $this->audit->log(ctx: $ctx, category: 'BUSINESS', action: 'batch.received', entityType: 'inventory_batch', entityRef: $ref, after: $after); 
            return $after;
        });

        return Response::json(201, $after);
    }

    public function adjust(Request $r): Response
    {
        $ctx = TenantContext::get(); 
        $this->authorization->requirePermission($ctx, 'inventory', 'adjust'); 
        $f = $ctx->requireFranchise(); 
        $ref = (string)$r->param('ref'); 
        $clean = Validation::validate($r->all(), ['delta_qty' => 'required|integer', 'reason' => 'required|string|min:2']); 
        
        $tx = new \App\Core\Transaction(\App\Core\Database::connection());
        $after = $tx->run(function() use ($ctx, $f, $ref, $clean) {
            $this->inventoryService->adjustStock($ctx->orgRef, $f, $ref, (int)$clean['delta_qty'], $clean['reason'], $ctx->userRef); 
            $after = $this->batchRepo->findByRef($f, $ref); 
            $this->audit->log(ctx: $ctx, category: 'BUSINESS', action: 'batch.adjusted', entityType: 'inventory_batch', entityRef: $ref, after: $after, reason: $clean['reason']); 
            return $after;
        });

        return Response::json(200, $after);
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
        $ctx = TenantContext::get(); $this->authorization->requirePermission($ctx, 'inventory', 'release'); $orderRef = (string)$r->param('order_ref'); $this->fefo->releaseOrderStock($ctx->orgRef, $ctx->requireFranchise(), $orderRef, $ctx->userRef); $this->audit->log(ctx: $ctx, category: 'BUSINESS', action: 'reservation.released', entityType: 'order', entityRef: $orderRef); return Response::json(200, ['order_ref' => $orderRef, 'status' => 'RELEASED']);
    }

    public function consume(Request $r): Response
    {
        $ctx = TenantContext::get(); $this->authorization->requirePermission($ctx, 'inventory', 'reserve'); $orderRef = (string)$r->param('order_ref'); $this->fefo->consumeOrderStock($ctx->orgRef, $ctx->requireFranchise(), $orderRef, $ctx->userRef); $this->audit->log(ctx: $ctx, category: 'BUSINESS', action: 'reservation.consumed', entityType: 'order', entityRef: $orderRef); return Response::json(200, ['order_ref' => $orderRef, 'status' => 'CONSUMED']);
    }

    /** BE-080: Movement ledger — all stock movements across batches. */
    public function movements(Request $r): Response
    {
        $ctx = TenantContext::get();
        $this->authorization->requirePermission($ctx, 'inventory', 'view');
        $f = $ctx->requireFranchise();
        $page = (int)$r->query('page', 1);
        $perPage = min((int)$r->query('per_page', 50), 200);
        $offset = ($page - 1) * $perPage;

        $where = ['im.franchise_ref = ?'];
        $params = [$f];

        if ($r->query('batch_ref')) { $where[] = 'im.batch_ref = ?'; $params[] = $r->query('batch_ref'); }
        if ($r->query('product_ref')) { $where[] = 'ib.product_ref = ?'; $params[] = $r->query('product_ref'); }
        if ($r->query('movement_type')) { $where[] = 'im.movement_type = ?'; $params[] = $r->query('movement_type'); }
        if ($r->query('from_date')) { $where[] = 'im.created_at >= ?'; $params[] = $r->query('from_date') . ' 00:00:00'; }
        if ($r->query('to_date')) { $where[] = 'im.created_at <= ?'; $params[] = $r->query('to_date') . ' 23:59:59'; }

        $clause = implode(' AND ', $where);
        $db = \App\Core\Container::getInstance()->make(\App\Core\Database::class);
        $total = (int)$db->fetchColumn("SELECT COUNT(*) FROM inventory_movements im JOIN inventory_batches ib ON ib.franchise_ref = im.franchise_ref AND ib.batch_ref = im.batch_ref WHERE {$clause}", $params);
        $items = $db->fetchAll("SELECT im.*, ib.batch_no, ib.product_ref, p.product_name, p.sku FROM inventory_movements im JOIN inventory_batches ib ON ib.franchise_ref = im.franchise_ref AND ib.batch_ref = im.batch_ref JOIN products p ON p.franchise_ref = ib.franchise_ref AND p.product_ref = ib.product_ref WHERE {$clause} ORDER BY im.created_at DESC LIMIT {$perPage} OFFSET {$offset}", $params);

        return Response::json(200, $items, [
            'page' => $page, 'per_page' => $perPage, 'total' => $total, 'total_pages' => (int)ceil($total / $perPage),
        ]);
    }

    /** BE-081: Edit batch metadata (location, expiry, batch_no). */
    public function editBatch(Request $r): Response
    {
        $ctx = TenantContext::get();
        $this->authorization->requirePermission($ctx, 'inventory', 'adjust');
        $f = $ctx->requireFranchise();
        $ref = (string)$r->param('ref');
        $batch = $this->batchRepo->findByRef($f, $ref);
        if (!$batch) throw new NotFoundException('BATCH_NOT_FOUND', 'Inventory batch not found.');

        $allowed = ['batch_no', 'expiry_date', 'manufacturing_date', 'location_code'];
        $data = [];
        foreach ($allowed as $field) {
            if ($r->input($field) !== null) $data[$field] = $r->input($field);
        }
        if (empty($data)) return Response::json(200, $batch);

        $db = \App\Core\Container::getInstance()->make(\App\Core\Database::class);
        $db->update('inventory_batches', $data, 'franchise_ref = ? AND batch_ref = ?', [$f, $ref]);
        $after = $this->batchRepo->findByRef($f, $ref);
        $this->audit->log(ctx: $ctx, category: 'BUSINESS', action: 'batch.edited', entityType: 'inventory_batch', entityRef: $ref, before: $batch, after: $after);
        return Response::json(200, $after);
    }

    /** BE-082: Stock transfer between warehouses/locations within same franchise. */
    public function transfer(Request $r): Response
    {
        $ctx = TenantContext::get();
        $this->authorization->requirePermission($ctx, 'inventory', 'adjust');
        $f = $ctx->requireFranchise();
        $clean = Validation::validate($r->all(), [
            'batch_ref' => 'required|string',
            'qty' => 'required|integer|min:1',
            'from_location' => 'required|string',
            'to_location' => 'required|string',
            'reason' => 'string',
        ]);

        $batch = $this->batchRepo->findByRef($f, $clean['batch_ref']);
        if (!$batch) throw new NotFoundException('BATCH_NOT_FOUND', 'Batch not found.');

        $available = (int)$batch['on_hand_qty'] - (int)$batch['reserved_qty'];
        if ((int)$clean['qty'] > $available) {
            throw new \App\Core\Exceptions\ValidationException('INSUFFICIENT_STOCK', 'Cannot transfer more than available unreserved stock.');
        }

        $db = \App\Core\Container::getInstance()->make(\App\Core\Database::class);
        $db->transaction(function () use ($db, $ctx, $f, $clean, $batch) {
            // Record outbound movement
            $this->movementRepo->record([
                'movement_ref' => \App\Core\RefGenerator::generate('mov'),
                'org_ref' => $ctx->orgRef, 'franchise_ref' => $f, 'batch_ref' => $clean['batch_ref'],
                'movement_type' => 'TRANSFER_OUT', 'qty' => (int)$clean['qty'],
                'reference_type' => 'TRANSFER', 'reference_ref' => $clean['to_location'],
                'remarks' => 'Transfer from ' . $clean['from_location'] . ' to ' . $clean['to_location'] . '. ' . ($clean['reason'] ?? ''),
                'created_by_ref' => $ctx->userRef,
            ]);
            // Record inbound movement
            $this->movementRepo->record([
                'movement_ref' => \App\Core\RefGenerator::generate('mov'),
                'org_ref' => $ctx->orgRef, 'franchise_ref' => $f, 'batch_ref' => $clean['batch_ref'],
                'movement_type' => 'TRANSFER_IN', 'qty' => (int)$clean['qty'],
                'reference_type' => 'TRANSFER', 'reference_ref' => $clean['from_location'],
                'remarks' => 'Transfer from ' . $clean['from_location'] . ' to ' . $clean['to_location'] . '. ' . ($clean['reason'] ?? ''),
                'created_by_ref' => $ctx->userRef,
            ]);
            // Update batch location to target
            $db->update('inventory_batches', ['location_code' => $clean['to_location']], 'franchise_ref = ? AND batch_ref = ?', [$f, $clean['batch_ref']]);
        });

        $this->audit->log(ctx: $ctx, category: 'BUSINESS', action: 'batch.transferred', entityType: 'inventory_batch', entityRef: $clean['batch_ref']);
        return Response::json(200, ['batch_ref' => $clean['batch_ref'], 'qty' => $clean['qty'], 'from' => $clean['from_location'], 'to' => $clean['to_location'], 'status' => 'TRANSFERRED']);
    }

    public function stockSummary(Request $r): Response
    {
        $ctx = TenantContext::get();
        $this->authorization->requirePermission($ctx, 'inventory', 'view');
        $f = $ctx->requireFranchise();

        $db = \App\Core\Container::getInstance()->make(\App\Core\Database::class);
        $sql = "SELECT p.product_ref, p.product_name, p.sku,
                       COALESCE(SUM(b.on_hand_qty), 0) as total_on_hand,
                       COALESCE(SUM(b.reserved_qty), 0) as total_reserved,
                       COALESCE(SUM(b.on_hand_qty - b.reserved_qty), 0) as total_available,
                       COALESCE(SUM(b.damaged_qty), 0) as total_damaged,
                       COUNT(b.id) as batch_count
                FROM products p
                LEFT JOIN inventory_batches b ON b.franchise_ref = p.franchise_ref AND b.product_ref = p.product_ref AND b.status = 'SALEABLE'
                WHERE p.franchise_ref = ?
                GROUP BY p.product_ref, p.product_name, p.sku
                ORDER BY p.product_name ASC";
        $rows = $db->fetchAll($sql, [$f]);
        return Response::json(200, $rows);
    }

    public function quarantine(Request $r): Response
    {
        $ctx = TenantContext::get();
        $this->authorization->requirePermission($ctx, 'inventory', 'adjust');
        $f = $ctx->requireFranchise();
        $ref = (string)$r->param('ref');
        $batch = $this->batchRepo->findByRef($f, $ref);
        if (!$batch) throw new NotFoundException('BATCH_NOT_FOUND', 'Inventory batch not found.');

        $reason = (string)$r->input('reason', 'Batch moved to quarantine');
        $db = \App\Core\Container::getInstance()->make(\App\Core\Database::class);
        $db->update('inventory_batches', ['status' => 'QUARANTINE'], 'franchise_ref = ? AND batch_ref = ?', [$f, $ref]);

        $after = $this->batchRepo->findByRef($f, $ref);
        $this->audit->log(ctx: $ctx, category: 'BUSINESS', action: 'batch.quarantined', entityType: 'inventory_batch', entityRef: $ref, before: $batch, after: $after, reason: $reason);
        return Response::json(200, $after);
    }

    public function unquarantine(Request $r): Response
    {
        $ctx = TenantContext::get();
        $this->authorization->requirePermission($ctx, 'inventory', 'adjust');
        $f = $ctx->requireFranchise();
        $ref = (string)$r->param('ref');
        $batch = $this->batchRepo->findByRef($f, $ref);
        if (!$batch) throw new NotFoundException('BATCH_NOT_FOUND', 'Inventory batch not found.');

        $reason = (string)$r->input('reason', 'Batch released from quarantine to saleable');
        $db = \App\Core\Container::getInstance()->make(\App\Core\Database::class);
        $db->update('inventory_batches', ['status' => 'SALEABLE'], 'franchise_ref = ? AND batch_ref = ?', [$f, $ref]);

        $after = $this->batchRepo->findByRef($f, $ref);
        $this->audit->log(ctx: $ctx, category: 'BUSINESS', action: 'batch.unquarantined', entityType: 'inventory_batch', entityRef: $ref, before: $batch, after: $after, reason: $reason);
        return Response::json(200, $after);
    }

    public function damage(Request $r): Response
    {
        $ctx = TenantContext::get();
        $this->authorization->requirePermission($ctx, 'inventory', 'adjust');
        $f = $ctx->requireFranchise();
        $ref = (string)$r->param('ref');
        $batch = $this->batchRepo->findByRef($f, $ref);
        if (!$batch) throw new NotFoundException('BATCH_NOT_FOUND', 'Inventory batch not found.');

        $clean = Validation::validate($r->all(), [
            'qty'    => 'required|integer|min:1',
            'reason' => 'required|string|min:2',
        ]);

        $qty = (int)$clean['qty'];
        $available = (int)$batch['on_hand_qty'] - (int)$batch['reserved_qty'];
        if ($qty > $available) {
            throw new \App\Core\Exceptions\ValidationException('INSUFFICIENT_STOCK', 'Damaged quantity exceeds available stock.');
        }

        $db = \App\Core\Container::getInstance()->make(\App\Core\Database::class);
        $db->transaction(function() use ($db, $ctx, $f, $ref, $qty, $clean) {
            $db->prepare(
                "UPDATE inventory_batches
                 SET on_hand_qty = on_hand_qty - ?, damaged_qty = damaged_qty + ?, updated_at = NOW()
                 WHERE franchise_ref = ? AND batch_ref = ?"
            )->execute([$qty, $qty, $f, $ref]);

            $this->movementRepo->record([
                'movement_ref'   => \App\Core\RefGenerator::generate('mov'),
                'org_ref'        => $ctx->orgRef,
                'franchise_ref'  => $f,
                'batch_ref'      => $ref,
                'movement_type'  => 'DAMAGE',
                'qty'            => $qty,
                'reference_type' => 'MANUAL_DAMAGE',
                'reference_ref'  => null,
                'remarks'        => $clean['reason'],
                'created_by_ref' => $ctx->userRef,
            ]);
        });

        $after = $this->batchRepo->findByRef($f, $ref);
        $this->audit->log(ctx: $ctx, category: 'BUSINESS', action: 'batch.damaged', entityType: 'inventory_batch', entityRef: $ref, before: $batch, after: $after, reason: $clean['reason']);
        return Response::json(200, $after);
    }

    public function batchMovements(Request $r): Response
    {
        $ctx = TenantContext::get();
        $this->authorization->requirePermission($ctx, 'inventory', 'view');
        $f = $ctx->requireFranchise();
        $ref = (string)$r->param('ref');
        $batch = $this->batchRepo->findByRef($f, $ref);
        if (!$batch) throw new NotFoundException('BATCH_NOT_FOUND', 'Inventory batch not found.');

        return Response::json(200, $this->movementRepo->listByBatch($f, $ref));
    }

    public function expired(Request $r): Response
    {
        $ctx = TenantContext::get();
        $this->authorization->requirePermission($ctx, 'inventory', 'view');
        $f = $ctx->requireFranchise();

        $db = \App\Core\Container::getInstance()->make(\App\Core\Database::class);
        $today = date('Y-m-d');
        $sql = "SELECT b.*, p.product_name, p.sku
                FROM inventory_batches b
                JOIN products p ON p.franchise_ref = b.franchise_ref AND p.product_ref = b.product_ref
                WHERE b.franchise_ref = ? AND b.expiry_date < ? AND b.on_hand_qty > 0
                ORDER BY b.expiry_date ASC";
        return Response::json(200, $db->fetchAll($sql, [$f, $today]));
    }
}
