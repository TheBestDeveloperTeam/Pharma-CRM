<?php
declare(strict_types=1);
namespace App\Domain\Inventory;

use App\Core\Database;
use App\Core\RefGenerator;
use App\Core\Exceptions\ValidationException;
use App\Repositories\Contracts\InventoryBatchRepositoryInterface;
use App\Repositories\Contracts\InventoryMovementRepositoryInterface;

final class InventoryService
{
    public function __construct(
        private Database $db,
        private InventoryBatchRepositoryInterface $batchRepo,
        private InventoryMovementRepositoryInterface $movementRepo,
    ) {}

    public function receiveGoods(
        string $orgRef,
        string $franchiseRef,
        string $productRef,
        string $batchNo,
        string $expiryDate,
        int $qty,
        ?string $mfgDate,
        ?string $locationCode,
        string $actorRef
    ): string {
        if ($qty <= 0) {
            throw new ValidationException('INVALID_QTY', 'Received quantity must be greater than zero.');
        }

        // Check if batch already exists for this product in this franchise
        $existing = $this->batchRepo->findByNo($franchiseRef, $productRef, $batchNo);
        if ($existing) {
            throw new ValidationException('DUPLICATE_BATCH', "Batch number '{$batchNo}' already exists for this product.");
        }

        $batchRef = RefGenerator::generate('bat');

        $this->db->transaction(function() use (
            $batchRef, $orgRef, $franchiseRef, $productRef, $batchNo,
            $expiryDate, $qty, $mfgDate, $locationCode, $actorRef
        ) {
            $this->batchRepo->create([
                'batch_ref'          => $batchRef,
                'org_ref'            => $orgRef,
                'franchise_ref'      => $franchiseRef,
                'product_ref'        => $productRef,
                'batch_no'           => $batchNo,
                'manufacturing_date' => $mfgDate,
                'expiry_date'        => $expiryDate,
                'received_qty'       => $qty,
                'location_code'      => $locationCode,
                'status'             => 'SALEABLE',
                'created_by_ref'     => $actorRef,
            ]);

            $this->movementRepo->record([
                'movement_ref'   => RefGenerator::generate('mov'),
                'org_ref'        => $orgRef,
                'franchise_ref'  => $franchiseRef,
                'batch_ref'      => $batchRef,
                'movement_type'  => 'RECEIPT',
                'qty'            => $qty,
                'reference_type' => 'GRN',
                'reference_ref'  => null,
                'remarks'        => 'Initial batch receipt',
                'created_by_ref' => $actorRef,
            ]);
        });

        return $batchRef;
    }

    public function adjustStock(
        string $orgRef,
        string $franchiseRef,
        string $batchRef,
        int $deltaQty,
        string $reason,
        string $actorRef
    ): bool {
        $batch = $this->batchRepo->findByRef($franchiseRef, $batchRef);
        if (!$batch) {
            throw new ValidationException('BATCH_NOT_FOUND', 'Inventory batch does not exist.');
        }

        $version = (int)$batch['version'];
        $movType = $deltaQty >= 0 ? 'ADJUST' : 'DAMAGE';

        return $this->db->transaction(function() use (
            $orgRef, $franchiseRef, $batchRef, $deltaQty, $reason, $actorRef, $version, $movType
        ) {
            $updated = $this->batchRepo->updateQty($franchiseRef, $batchRef, $deltaQty, 0, $version);
            if (!$updated) {
                throw new ValidationException('CONCURRENT_MODIFICATION', 'Batch was modified concurrently or insufficient stock.');
            }

            $this->movementRepo->record([
                'movement_ref'   => RefGenerator::generate('mov'),
                'org_ref'        => $orgRef,
                'franchise_ref'  => $franchiseRef,
                'batch_ref'      => $batchRef,
                'movement_type'  => $movType,
                'qty'            => $deltaQty,
                'reference_type' => 'MANUAL_ADJUSTMENT',
                'reference_ref'  => null,
                'remarks'        => $reason,
                'created_by_ref' => $actorRef,
            ]);

            return true;
        });
    }

    public function listNearExpiry(string $franchiseRef, int $days = 90): array
    {
        return $this->batchRepo->listNearExpiry($franchiseRef, $days);
    }
}
