<?php
declare(strict_types=1);
namespace App\Http\Controllers\Api\V1\Admin;

use App\Core\Request;
use App\Core\Response;
use App\Core\Validation;
use App\Core\TenantContext;
use App\Core\Exceptions\NotFoundException;
use App\Domain\Inventory\InventoryService;
use App\Repositories\Contracts\InventoryBatchRepositoryInterface;
use App\Repositories\Contracts\InventoryMovementRepositoryInterface;

final class InventoryController
{
    public function __construct(
        private InventoryService $inventoryService,
        private InventoryBatchRepositoryInterface $batchRepo,
        private InventoryMovementRepositoryInterface $movementRepo,
    ) {}

    public function showBatch(Request $r, string $ref): Response
    {
        $ctx = TenantContext::get();
        $batch = $this->batchRepo->findByRef($ctx->franchiseRef, $ref);
        if (!$batch) {
            throw new NotFoundException('BATCH_NOT_FOUND', 'Inventory batch not found.');
        }

        $movements = $this->movementRepo->listByBatch($ctx->franchiseRef, $ref);
        $batch['movements'] = $movements;

        return Response::json(['data' => $batch]);
    }

    public function receive(Request $r): Response
    {
        $ctx = TenantContext::get();
        $clean = Validation::validate($r->all(), [
            'product_ref' => 'required|string',
            'batch_no'    => 'required|string',
            'expiry_date' => 'required|string',
            'qty'         => 'required|integer',
        ]);

        $batchRef = $this->inventoryService->receiveGoods(
            $ctx->orgRef,
            $ctx->franchiseRef,
            $clean['product_ref'],
            $clean['batch_no'],
            $clean['expiry_date'],
            (int)$clean['qty'],
            $r->input('manufacturing_date'),
            $r->input('location_code'),
            $ctx->userRef
        );

        $batch = $this->batchRepo->findByRef($ctx->franchiseRef, $batchRef);
        return Response::json(['data' => $batch], 201);
    }

    public function adjust(Request $r, string $ref): Response
    {
        $ctx = TenantContext::get();
        $clean = Validation::validate($r->all(), [
            'delta_qty' => 'required|integer',
            'reason'    => 'required|string',
        ]);

        $this->inventoryService->adjustStock(
            $ctx->orgRef,
            $ctx->franchiseRef,
            $ref,
            (int)$clean['delta_qty'],
            $clean['reason'],
            $ctx->userRef
        );

        $batch = $this->batchRepo->findByRef($ctx->franchiseRef, $ref);
        return Response::json(['data' => $batch]);
    }

    public function nearExpiry(Request $r): Response
    {
        $ctx = TenantContext::get();
        $days = (int)$r->query('days', 90);
        $list = $this->inventoryService->listNearExpiry($ctx->franchiseRef, $days);
        return Response::json(['data' => $list]);
    }
}
