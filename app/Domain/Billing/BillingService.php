<?php
declare(strict_types=1);

namespace App\Domain\Billing;

use App\Core\{Database, RefGenerator, SequenceService};
use App\Core\Exceptions\{ConflictException, ValidationException};
use App\Repositories\Contracts\{InvoiceRepositoryInterface, OrderRepositoryInterface, PartyRepositoryInterface};

final class BillingService
{
    public function __construct(private Database $db, private InvoiceRepositoryInterface $invoiceRepo, private OrderRepositoryInterface $orderRepo, private PartyRepositoryInterface $partyRepo, private SequenceService $sequenceService, private GstCalculator $gst) {}

    public function generateInvoice(string $orgRef, string $franchiseRef, string $orderRef, string $actorRef): array
    {
        return $this->db->transaction(function () use ($orgRef, $franchiseRef, $orderRef, $actorRef): array {
            $order = $this->orderRepo->findByRefForUpdate($franchiseRef, $orderRef);
            if (!$order) throw new ValidationException('ORDER_NOT_FOUND', 'Order does not exist.');
            if ($order['status'] !== 'CONFIRMED') throw new ValidationException('INVALID_STATUS_FOR_BILLING', 'Only CONFIRMED orders are eligible for invoice generation.');
            if ($this->invoiceRepo->findByOrderRef($franchiseRef, $orderRef)) throw new ConflictException('INVOICE_ALREADY_EXISTS', 'An invoice already exists for this order.');
            $party = $this->partyRepo->findByRef($franchiseRef, $order['party_ref']);
            if (!$party || $party['status'] !== 'ACTIVE') throw new ValidationException('INVALID_PARTY', 'Billing party is inactive or unavailable.');
            if (!$this->orderRepo->getItems($franchiseRef, $orderRef)) throw new ValidationException('EMPTY_ORDER', 'Order has no billable lines.');

            $franchise = $this->db->fetchRow('SELECT gstin FROM franchises WHERE franchise_ref = ?', [$franchiseRef]);
            $franchiseGstin = $franchise['gstin'] ?? '';
            $partyGstin = $party['gstin'] ?? '';
            
            $jurisdiction = GstCalculator::INTRASTATE;
            if (strlen($franchiseGstin) >= 2 && strlen($partyGstin) >= 2) {
                if (substr($franchiseGstin, 0, 2) !== substr($partyGstin, 0, 2)) {
                    $jurisdiction = GstCalculator::INTERSTATE;
                }
            }
            
            $orderItems = $this->orderRepo->getItems($franchiseRef, $orderRef);
            $linesForGst = [];
            foreach ($orderItems as $item) {
                $taxableAmount = (float)$item['total_price'];
                $linesForGst[] = [
                    'taxable_amount' => $taxableAmount,
                    'gst_percent' => $item['gst_percent'] ?? 0,
                    'order_item_ref' => $item['item_ref']
                ];
            }
            
            $gstResult = $this->gst->calculate($linesForGst, $jurisdiction);

            $invoiceRef = RefGenerator::generate('inv');
            $invoiceNo = $this->sequenceService->next($franchiseRef, 'INVOICE', date('Y-m-d'));
            $this->invoiceRepo->create([
                'invoice_ref' => $invoiceRef, 'invoice_no' => $invoiceNo, 'org_ref' => $orgRef, 'franchise_ref' => $franchiseRef,
                'order_ref' => $orderRef, 'party_ref' => $order['party_ref'], 'invoice_date' => date('Y-m-d'), 'due_date' => null,
                'bill_to_snapshot' => ['firm_name' => $party['firm_name'], 'gstin' => $partyGstin, 'address' => $order['billing_address'] ?? $party['billing_address'] ?? null],
                'ship_to_snapshot' => ['address' => $order['shipping_address'] ?? $party['shipping_address'] ?? null, 'pincode' => $order['shipping_pincode'] ?? $party['pincode'] ?? null],
                'subtotal' => $gstResult['taxable_total'], 'discount_total' => '0.00', 'taxable_total' => $gstResult['taxable_total'], 
                'cgst_total' => $gstResult['cgst_total'], 'sgst_total' => $gstResult['sgst_total'], 'igst_total' => $gstResult['igst_total'], 
                'gst_total' => $gstResult['tax_total'], 'rounding_adjustment' => '0.00', 'grand_total' => $gstResult['grand_total'], 
                'tax_policy_code' => $jurisdiction, 'created_by_ref' => $actorRef,
            ], $gstResult['lines']);
            return ['invoice_ref' => $invoiceRef, 'invoice_no' => $invoiceNo, 'status' => 'POSTED'];
        });
    }

    public function cancelInvoice(string $franchiseRef, string $invoiceRef, string $actorRef, string $reason): array
    {
        return $this->db->transaction(function () use ($franchiseRef, $invoiceRef, $actorRef, $reason): array {
            $invoice = $this->invoiceRepo->findByRefForUpdate($franchiseRef, $invoiceRef);
            if (!$invoice) throw new ValidationException('INVOICE_NOT_FOUND', 'Invoice not found.');
            if ($invoice['status'] !== 'POSTED') throw new ValidationException('INVALID_INVOICE_TRANSITION', 'Only POSTED invoices can be cancelled.');
            if ((float)$invoice['paid_total'] > 0) throw new ValidationException('INVOICE_PAID', 'Cannot cancel an invoice that has been partially or fully paid.');
            if ((int)$this->db->fetchColumn('SELECT COUNT(*) FROM dispatches WHERE franchise_ref = ? AND invoice_ref = ?', [$franchiseRef, $invoiceRef]) > 0) throw new ValidationException('INVOICE_DISPATCHED', 'Cannot cancel an invoice that has a recorded dispatch.');
            if (!$this->invoiceRepo->cancelPosted($franchiseRef, $invoiceRef, $actorRef, $reason)) throw new ConflictException('INVOICE_STATE_CHANGED', 'Invoice changed concurrently.');
            return ['invoice_ref' => $invoiceRef, 'status' => 'CANCELLED'];
        });
    }
}
