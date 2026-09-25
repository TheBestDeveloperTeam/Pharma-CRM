<?php
declare(strict_types=1);
namespace App\Domain\Inventory;

use App\Core\Database;
use App\Core\RefGenerator;
use App\Core\Exceptions\ValidationException;
use App\Repositories\Contracts\InventoryBatchRepositoryInterface;
use App\Repositories\Contracts\StockReservationRepositoryInterface;
use App\Repositories\Contracts\InventoryMovementRepositoryInterface;

final class FefoAllocator
{
    public function __construct(
        private Database $db,
        private InventoryBatchRepositoryInterface $batchRepo,
        private StockReservationRepositoryInterface $reservationRepo,
        private InventoryMovementRepositoryInterface $movementRepo,
    ) {}

    /**
     * Allocates stock for multiple line items in a deterministic, deadlock-safe manner.
     * Line items: [['order_item_ref' => string, 'product_ref' => string, 'qty' => int, 'min_shelf_days' => int]]
     */
    public function allocate(
        string $orgRef,
        string $franchiseRef,
        string $orderRef,
        array $lineItems,
        string $actorRef,
        bool $transactional = true
    ): array {
        // Deadlock prevention: sort lines deterministically by product_ref
        usort($lineItems, fn($a, $b) => strcmp($a['product_ref'], $b['product_ref']));

        $reservationsCreated = [];

        $work = function() use (
            $orgRef, $franchiseRef, $orderRef, $lineItems, $actorRef, &$reservationsCreated
        ) {
            foreach ($lineItems as $line) {
                $productRef = $line['product_ref'];
                $itemRef = $line['order_item_ref'];
                $qtyNeeded = (int)$line['qty'];
                $minShelfDays = (int)($line['min_shelf_days'] ?? 0);

                if ($qtyNeeded <= 0) {
                    continue;
                }

                // Query saleable batches ordered by expiry_date ASC, manufacturing_date ASC, id ASC
                $batches = $this->batchRepo->getSaleableBatches($franchiseRef, $productRef, $minShelfDays);
                $remaining = $qtyNeeded;

                foreach ($batches as $batch) {
                    $available = (int)$batch['on_hand_qty'] - (int)$batch['reserved_qty'];
                    if ($available <= 0) {
                        continue;
                    }

                    $take = min($remaining, $available);
                    $resRef = RefGenerator::generate('res');

                    // Update batch with optimistic concurrency version check
                    $updated = $this->batchRepo->updateQty(
                        $franchiseRef,
                        $batch['batch_ref'],
                        0, // onHand delta
                        $take, // reserved delta
                        (int)$batch['version']
                    );

                    if (!$updated) {
                        throw new ValidationException('CONCURRENT_STOCK_ERROR', "Batch {$batch['batch_no']} was modified concurrently.");
                    }

                    // Create stock reservation row
                    $this->reservationRepo->create([
                        'reservation_ref' => $resRef,
                        'org_ref'         => $orgRef,
                        'franchise_ref'   => $franchiseRef,
                        'batch_ref'       => $batch['batch_ref'],
                        'order_ref'       => $orderRef,
                        'order_item_ref'  => $itemRef,
                        'reserved_qty'    => $take,
                        'expires_at'      => date('Y-m-d H:i:s', time() + (86400 * 3)), // 3 days hold
                    ]);

                    // Record movement
                    $this->movementRepo->record([
                        'movement_ref'   => RefGenerator::generate('mov'),
                        'org_ref'        => $orgRef,
                        'franchise_ref'  => $franchiseRef,
                        'batch_ref'      => $batch['batch_ref'],
                        'movement_type'  => 'RESERVE',
                        'qty'            => $take,
                        'reference_type' => 'ORDER',
                        'reference_ref'  => $orderRef,
                        'remarks'        => "Stock reserved for order {$orderRef}",
                        'created_by_ref' => $actorRef,
                    ]);

                    $reservationsCreated[] = [
                        'reservation_ref' => $resRef,
                        'batch_ref'       => $batch['batch_ref'],
                        'batch_no'        => $batch['batch_no'],
                        'qty'             => $take,
                        'expiry_date'     => $batch['expiry_date'],
                    ];

                    $remaining -= $take;
                    if ($remaining === 0) {
                        break;
                    }
                }

                if ($remaining > 0) {
                    throw new ValidationException(
                        'INSUFFICIENT_STOCK',
                        "Insufficient stock for product {$productRef}. Short by {$remaining} units."
                    );
                }
            }

            return $reservationsCreated;
        };
        return $transactional ? $this->db->transaction($work) : $work();
    }

    /**
     * Releases active reservations back into saleable inventory (e.g. on order cancellation/rejection).
     */
    public function releaseOrderStock(string $orgRef, string $franchiseRef, string $orderRef, string $actorRef, bool $transactional = true): void
    {
        $work = function() use ($orgRef, $franchiseRef, $orderRef, $actorRef): void {
            $reservations = $this->reservationRepo->getActiveForOrder($franchiseRef, $orderRef);
            foreach ($reservations as $res) {
                $batch = $this->batchRepo->findByRef($franchiseRef, $res['batch_ref']);
                if ($batch) {
                    $updated = $this->batchRepo->updateQty(
                        $franchiseRef,
                        $res['batch_ref'],
                        0,
                        -(int)$res['reserved_qty'],
                        (int)$batch['version']
                    );
                    if (!$updated) throw new ValidationException('CONCURRENT_STOCK_ERROR', "Batch {$res['batch_ref']} changed while releasing stock.");

                    $this->movementRepo->record([
                        'movement_ref'   => RefGenerator::generate('mov'),
                        'org_ref'        => $orgRef,
                        'franchise_ref'  => $franchiseRef,
                        'batch_ref'      => $res['batch_ref'],
                        'movement_type'  => 'RELEASE',
                        'qty'            => (int)$res['reserved_qty'],
                        'reference_type' => 'ORDER_RELEASE',
                        'reference_ref'  => $orderRef,
                        'remarks'        => "Stock reservation released for order {$orderRef}",
                        'created_by_ref' => $actorRef,
                    ]);
                }

                $this->reservationRepo->release($franchiseRef, $res['reservation_ref']);
            }
        };
        if ($transactional) $this->db->transaction($work); else $work();
    }

    public function consumeOrderStock(string $orgRef, string $franchiseRef, string $orderRef, string $actorRef): void
    {
        $this->db->transaction(function () use ($orgRef, $franchiseRef, $orderRef, $actorRef): void {
            $reservations = $this->reservationRepo->getActiveForOrder($franchiseRef, $orderRef);
            foreach ($reservations as $res) {
                $batch = $this->batchRepo->findByRef($franchiseRef, $res['batch_ref']); if (!$batch) throw new ValidationException('BATCH_NOT_FOUND', 'Reserved batch not found.');
                if (!$this->batchRepo->updateQty($franchiseRef, $res['batch_ref'], -(int)$res['reserved_qty'], -(int)$res['reserved_qty'], (int)$batch['version'])) throw new ValidationException('CONCURRENT_STOCK_ERROR', 'Batch changed while consuming reservation.');
                if (!$this->reservationRepo->consume($franchiseRef, $res['reservation_ref'])) throw new ValidationException('RESERVATION_STATE_CHANGED', 'Reservation is no longer active.');
                $this->movementRepo->record(['movement_ref' => RefGenerator::generate('mov'), 'org_ref' => $orgRef, 'franchise_ref' => $franchiseRef, 'batch_ref' => $res['batch_ref'], 'movement_type' => 'SALE', 'qty' => (int)$res['reserved_qty'], 'reference_type' => 'ORDER', 'reference_ref' => $orderRef, 'remarks' => "Reservation consumed for order {$orderRef}", 'created_by_ref' => $actorRef]);
            }
        });
    }
}
