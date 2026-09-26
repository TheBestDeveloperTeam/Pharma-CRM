<?php
declare(strict_types=1);

namespace App\Domain\Billing;

use App\Core\{Database, RefGenerator, SequenceService};
use App\Core\Exceptions\{ConflictException, ValidationException};
use App\Repositories\Contracts\{InvoiceRepositoryInterface, OrderRepositoryInterface, PartyRepositoryInterface};
use App\Support\Money;

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

            $supplierState = $this->db->fetchColumn('SELECT state_ref FROM franchises WHERE franchise_ref = ? FOR UPDATE', [$franchiseRef]);
            if (!$supplierState) throw new ValidationException('SUPPLIER_STATE_REQUIRED', 'Supplier state must be configured before invoicing.');
            $placeState = $order['shipping_pincode'] ? $this->db->fetchColumn('SELECT state_ref FROM pincodes WHERE pincode = ? LIMIT 1', [$order['shipping_pincode']]) : null;
            if (!$placeState) throw new ValidationException('PLACE_OF_SUPPLY_REQUIRED', 'A normalized shipping pincode/state is required before invoicing.');
            $jurisdiction = $supplierState === $placeState ? GstCalculator::INTRASTATE : GstCalculator::INTERSTATE;
            $sourceLines = $this->db->fetchAll("SELECT oi.*,p.product_name,p.sku,p.hsn_code,p.gst_percent product_gst,b.batch_ref,b.batch_no,b.expiry_date,sr.reserved_qty FROM order_items oi JOIN products p ON p.franchise_ref=oi.franchise_ref AND p.product_ref=oi.product_ref JOIN stock_reservations sr ON sr.franchise_ref=oi.franchise_ref AND sr.order_ref=oi.order_ref AND sr.order_item_ref=oi.item_ref AND sr.status='ACTIVE' JOIN inventory_batches b ON b.franchise_ref=sr.franchise_ref AND b.batch_ref=sr.batch_ref WHERE oi.franchise_ref=? AND oi.order_ref=?", [$franchiseRef,$orderRef]);
            if (!$sourceLines) throw new ValidationException('ACTIVE_RESERVATION_REQUIRED', 'A billable order requires active FEFO reservations.');
            $taxInput=[];foreach($sourceLines as $line){if($line['product_gst']===null)throw new ValidationException('PRODUCT_GST_REQUIRED','Every invoiced product requires a GST rate.');$taxInput[]=['taxable_amount'=>Money::toDecimal(Money::fromDecimal($line['rate'])*(int)$line['reserved_qty']-Money::fromDecimal($line['discount'])),'gst_percent'=>$line['product_gst']];}
            $tax=$this->gst->calculate($taxInput,$jurisdiction);$items=[];foreach($sourceLines as $i=>$line){$t=$tax['lines'][$i];$half=Money::toDecimal(intdiv(Money::fromDecimal($line['product_gst']),2));$items[]=['item_ref'=>RefGenerator::generate('ii'),'order_item_ref'=>$line['item_ref'],'product_ref'=>$line['product_ref'],'product_name_snapshot'=>$line['product_name'],'sku_snapshot'=>$line['sku'],'hsn_snapshot'=>$line['hsn_code'],'batch_ref'=>$line['batch_ref'],'batch_no_snapshot'=>$line['batch_no'],'expiry_snapshot'=>$line['expiry_date'],'paid_qty'=>$line['reserved_qty'],'free_qty'=>0,'rate'=>$line['rate'],'discount'=>$line['discount'],'taxable_amount'=>$t['taxable_amount'],'gst_percent'=>$line['product_gst'],'cgst_percent'=>$jurisdiction===GstCalculator::INTRASTATE?$half:'0.00','sgst_percent'=>$jurisdiction===GstCalculator::INTRASTATE?$half:'0.00','igst_percent'=>$jurisdiction===GstCalculator::INTERSTATE?$line['product_gst']:'0.00','cgst_amount'=>$t['cgst_amount'],'sgst_amount'=>$t['sgst_amount'],'igst_amount'=>$t['igst_amount'],'total_tax'=>$t['total_tax'],'line_total'=>$t['line_total']];}
            $invoiceRef = RefGenerator::generate('inv');
            $invoiceNo = $this->sequenceService->next($franchiseRef, 'INVOICE', date('Y-m-d'));
            $this->invoiceRepo->create([
                'invoice_ref' => $invoiceRef, 'invoice_no' => $invoiceNo, 'org_ref' => $orgRef, 'franchise_ref' => $franchiseRef,
                'order_ref' => $orderRef, 'party_ref' => $order['party_ref'], 'invoice_date' => date('Y-m-d'), 'due_date' => ((int)($party['payment_terms_days'] ?? 0) > 0 ? date('Y-m-d',strtotime('+'.(int)$party['payment_terms_days'].' days')) : null),
                'bill_to_snapshot' => ['firm_name' => $party['firm_name'], 'gstin' => $party['gstin'] ?? null, 'address' => $order['billing_address'] ?? $party['billing_address'] ?? null],
                'ship_to_snapshot' => ['address' => $order['shipping_address'] ?? $party['shipping_address'] ?? null, 'pincode' => $order['shipping_pincode'] ?? $party['pincode'] ?? null],
                'subtotal' => $tax['taxable_total'], 'discount_total' => '0.00', 'taxable_total' => $tax['taxable_total'], 'cgst_total' => $tax['cgst_total'], 'sgst_total' => $tax['sgst_total'], 'igst_total' => $tax['igst_total'], 'gst_total' => $tax['tax_total'], 'rounding_adjustment' => '0.00', 'grand_total' => $tax['grand_total'], 'tax_policy_code' => $jurisdiction, 'supplier_state_ref'=>$supplierState,'place_of_supply_state_ref'=>$placeState,'created_by_ref' => $actorRef,
            ], $items);
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
