<?php
declare(strict_types=1);
namespace App\Domain\Billing;

use App\Core\Database;
use App\Core\RefGenerator;
use App\Core\SequenceService;
use App\Core\Exceptions\ValidationException;
use App\Repositories\Contracts\InvoiceRepositoryInterface;
use App\Repositories\Contracts\OrderRepositoryInterface;
use App\Repositories\Contracts\PartyRepositoryInterface;
use App\Repositories\Contracts\StockReservationRepositoryInterface;
use App\Repositories\Contracts\InventoryBatchRepositoryInterface;
use App\Repositories\Contracts\InventoryMovementRepositoryInterface;

final class BillingService
{
    public function __construct(
        private Database $db,
        private InvoiceRepositoryInterface $invoiceRepo,
        private OrderRepositoryInterface $orderRepo,
        private PartyRepositoryInterface $partyRepo,
        private StockReservationRepositoryInterface $reservationRepo,
        private InventoryBatchRepositoryInterface $batchRepo,
        private InventoryMovementRepositoryInterface $movementRepo,
        private SequenceService $sequenceService,
    ) {}

    public function generateInvoice(
        string $orgRef,
        string $franchiseRef,
        string $orderRef,
        string $actorRef
    ): array {
        $order = $this->orderRepo->findByRef($franchiseRef, $orderRef);
        if (!$order) {
            throw new ValidationException('ORDER_NOT_FOUND', 'Order does not exist.');
        }

        if (!in_array($order['status'], ['CONFIRMED', 'BILLING_PENDING'], true)) {
            throw new ValidationException('INVALID_STATUS_FOR_BILLING', "Order status {$order['status']} is not eligible for billing.");
        }

        // Check if invoice already exists for this order
        $existing = $this->invoiceRepo->findByOrderRef($franchiseRef, $orderRef);
        if ($existing) {
            return $existing;
        }

        $party = $this->partyRepo->findByRef($franchiseRef, $order['party_ref']);
        if (!$party) {
            throw new ValidationException('PARTY_NOT_FOUND', 'Party does not exist.');
        }

        // Get reservations for the order
        $reservations = $this->reservationRepo->getActiveForOrder($franchiseRef, $orderRef);
        if (empty($reservations)) {
            throw new ValidationException('NO_STOCK_RESERVATIONS', 'Order has no active stock reservations to bill.');
        }

        $orderItems = $this->orderRepo->getItems($franchiseRef, $orderRef);
        $orderItemMap = [];
        foreach ($orderItems as $it) {
            $orderItemMap[$it['item_ref']] = $it;
        }

        $invoiceRef = RefGenerator::generate('inv');

        return $this->db->transaction(function() use (
            $orgRef, $franchiseRef, $orderRef, $order, $party, $reservations, $orderItemMap, $invoiceRef, $actorRef
        ) {
            // Gapless sequential invoice number inside transaction
            $invoiceNo = $this->sequenceService->next($franchiseRef, 'INVOICE', date('Y-m-d'));

            $invoiceDate = date('Y-m-d');
            $paymentTerms = (int)($party['payment_terms_days'] ?? 0);
            $dueDate = $paymentTerms > 0 ? date('Y-m-d', strtotime("+{$paymentTerms} days")) : $invoiceDate;

            $billToSnapshot = [
                'firm_name' => $party['firm_name'],
                'contact_name' => $party['contact_name'],
                'mobile' => $party['mobile'],
                'email' => $party['email'],
                'gstin' => $party['gstin'],
                'drug_license_no' => $party['drug_license_no'],
                'address' => $party['billing_address'],
                'pincode' => $party['pincode'],
            ];

            $shipToSnapshot = [
                'shipping_address' => $order['shipping_address'] ?? $party['shipping_address'],
                'shipping_pincode' => $order['shipping_pincode'] ?? $party['pincode'],
            ];

            $invoiceItems = [];
            foreach ($reservations as $res) {
                $orderItem = $orderItemMap[$res['order_item_ref']] ?? null;
                if (!$orderItem) {
                    continue;
                }

                $batch = $this->batchRepo->findByRef($franchiseRef, $res['batch_ref']);
                if (!$batch) {
                    throw new ValidationException('BATCH_NOT_FOUND', "Batch {$res['batch_ref']} not found.");
                }

                $qty = (int)$res['reserved_qty'];

                // 1. Consume reservation row
                $this->reservationRepo->consume($franchiseRef, $res['reservation_ref']);

                // 2. Decrement physical stock: on_hand -= qty, reserved -= qty
                $updated = $this->batchRepo->updateQty(
                    $franchiseRef,
                    $batch['batch_ref'],
                    -$qty, // on_hand_qty decrement
                    -$qty, // reserved_qty decrement
                    (int)$batch['version']
                );

                if (!$updated) {
                    throw new ValidationException('BATCH_VERSION_MISMATCH', "Batch {$batch['batch_no']} modified concurrently.");
                }

                // 3. Record SALE movement
                $this->movementRepo->record([
                    'movement_ref'   => RefGenerator::generate('mov'),
                    'org_ref'        => $orgRef,
                    'franchise_ref'  => $franchiseRef,
                    'batch_ref'      => $batch['batch_ref'],
                    'movement_type'  => 'SALE',
                    'qty'            => -$qty,
                    'reference_type' => 'INVOICE',
                    'reference_ref'  => $invoiceRef,
                    'remarks'        => "Billed on invoice {$invoiceNo}",
                    'created_by_ref' => $actorRef,
                ]);

                // 4. Build frozen line snapshot
                $rate = (float)$orderItem['rate'];
                $lineTotal = round($rate * $qty, 2);

                $invoiceItems[] = [
                    'item_ref'              => RefGenerator::generate('iit'),
                    'order_item_ref'        => $orderItem['item_ref'],
                    'product_ref'           => $orderItem['product_ref'],
                    'product_name_snapshot' => $orderItem['product_name'],
                    'sku_snapshot'          => $orderItem['sku'],
                    'hsn_snapshot'          => $orderItem['hsn_code'] ?? null,
                    'batch_ref'             => $batch['batch_ref'],
                    'batch_no_snapshot'     => $batch['batch_no'],
                    'expiry_snapshot'       => $batch['expiry_date'],
                    'paid_qty'              => $qty,
                    'free_qty'              => 0,
                    'rate'                  => $rate,
                    'discount'              => 0.00,
                    'gst_percent'           => (float)$orderItem['gst_percent'],
                    'line_total'            => $lineTotal,
                ];
            }

            $invoiceData = [
                'invoice_ref'      => $invoiceRef,
                'invoice_no'       => $invoiceNo,
                'org_ref'          => $orgRef,
                'franchise_ref'    => $franchiseRef,
                'order_ref'        => $orderRef,
                'party_ref'        => $order['party_ref'],
                'invoice_date'     => $invoiceDate,
                'due_date'         => $dueDate,
                'bill_to_snapshot' => $billToSnapshot,
                'ship_to_snapshot' => $shipToSnapshot,
                'subtotal'         => $order['subtotal'],
                'discount_total'   => $order['discount_total'],
                'gst_total'        => $order['gst_total'],
                'grand_total'      => $order['grand_total'],
                'created_by_ref'   => $actorRef,
            ];

            $this->invoiceRepo->create($invoiceData, $invoiceItems);

            // Update order status to BILLED
            $this->orderRepo->updateStatus($franchiseRef, $orderRef, 'BILLED', $actorRef, "Invoiced via {$invoiceNo}");

            return [
                'invoice_ref' => $invoiceRef,
                'invoice_no'  => $invoiceNo,
                'grand_total' => $order['grand_total'],
                'due_date'    => $dueDate,
            ];
        });
    }
}
