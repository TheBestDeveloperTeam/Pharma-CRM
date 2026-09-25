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

            // The approved material has not resolved place-of-supply or GST
            // jurisdiction. Fail before allocating an invoice number or consuming
            // stock; this preserves both financial and inventory integrity.
            $this->gst->calculate([], null);

            // This path is intentionally unreachable until a configured policy
            // resolver is supplied. Keeping it here makes the transaction boundary
            // and immutable snapshot contract explicit without inventing policy.
            $invoiceRef = RefGenerator::generate('inv');
            $invoiceNo = $this->sequenceService->next($franchiseRef, 'INVOICE', date('Y-m-d'));
            $this->invoiceRepo->create([
                'invoice_ref' => $invoiceRef, 'invoice_no' => $invoiceNo, 'org_ref' => $orgRef, 'franchise_ref' => $franchiseRef,
                'order_ref' => $orderRef, 'party_ref' => $order['party_ref'], 'invoice_date' => date('Y-m-d'), 'due_date' => null,
                'bill_to_snapshot' => ['firm_name' => $party['firm_name'], 'gstin' => $party['gstin'] ?? null, 'address' => $order['billing_address'] ?? $party['billing_address'] ?? null],
                'ship_to_snapshot' => ['address' => $order['shipping_address'] ?? $party['shipping_address'] ?? null, 'pincode' => $order['shipping_pincode'] ?? $party['pincode'] ?? null],
                'subtotal' => '0.00', 'discount_total' => '0.00', 'taxable_total' => '0.00', 'cgst_total' => '0.00', 'sgst_total' => '0.00', 'igst_total' => '0.00', 'gst_total' => '0.00', 'rounding_adjustment' => '0.00', 'grand_total' => '0.00', 'tax_policy_code' => 'PENDING', 'created_by_ref' => $actorRef,
            ], []);
            return ['invoice_ref' => $invoiceRef, 'invoice_no' => $invoiceNo, 'status' => 'POSTED'];
        });
    }

    public function cancelInvoice(string $franchiseRef, string $invoiceRef, string $actorRef, string $reason): array
    {
        return $this->db->transaction(function () use ($franchiseRef, $invoiceRef, $actorRef, $reason): array {
            $invoice = $this->invoiceRepo->findByRefForUpdate($franchiseRef, $invoiceRef);
            if (!$invoice) throw new ValidationException('INVOICE_NOT_FOUND', 'Invoice not found.');
            if ($invoice['status'] !== 'POSTED') throw new ValidationException('INVALID_INVOICE_TRANSITION', 'Only POSTED invoices can be cancelled.');
            if ((float)$invoice['paid_total'] > 0) throw new ValidationException('INVOICE_CANCELLATION_POLICY_PENDING', 'Paid invoices require the future payment-reversal policy.');
            if ((int)$this->db->fetchColumn('SELECT COUNT(*) FROM dispatches WHERE franchise_ref = ? AND invoice_ref = ?', [$franchiseRef, $invoiceRef]) > 0) throw new ValidationException('INVOICE_CANCELLATION_POLICY_PENDING', 'Dispatched invoices require the future dispatch-reversal policy.');
            if (!$this->invoiceRepo->cancelPosted($franchiseRef, $invoiceRef, $actorRef, $reason)) throw new ConflictException('INVOICE_STATE_CHANGED', 'Invoice changed concurrently.');
            return ['invoice_ref' => $invoiceRef, 'status' => 'CANCELLED'];
        });
    }
}
