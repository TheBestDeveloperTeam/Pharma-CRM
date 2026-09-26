<?php
declare(strict_types=1);

namespace App\Domain\Dispatch;

use App\Core\{Database, RefGenerator, SequenceService};
use App\Core\Exceptions\{ConflictException, ValidationException};
use App\Domain\Orders\OrderStateMachine;
use App\Domain\Inventory\FefoAllocator;
use App\Repositories\Contracts\{DispatchRepositoryInterface, InvoiceRepositoryInterface, OrderRepositoryInterface};

final class DispatchService
{
    public function __construct(
        private Database $db, 
        private DispatchRepositoryInterface $dispatches, 
        private InvoiceRepositoryInterface $invoices, 
        private OrderRepositoryInterface $orders, 
        private SequenceService $sequences,
        private FefoAllocator $fefo
    ) {}


    public function create(string $orgRef, string $franchiseRef, string $invoiceRef, ?string $transporterRef, string $lrNumber, ?string $trackingUrl, int $boxes, ?string $remarks, string $actorRef): array
    {
        return $this->db->transaction(function () use ($orgRef, $franchiseRef, $invoiceRef, $transporterRef, $lrNumber, $trackingUrl, $boxes, $remarks, $actorRef): array {
            $invoice = $this->invoices->findByRefForUpdate($franchiseRef, $invoiceRef);
            if (!$invoice) throw new ValidationException('INVOICE_REQUIRED', 'A valid posted invoice is required before dispatch.');
            if ($invoice['status'] !== 'POSTED') throw new ValidationException('INVALID_INVOICE', 'Only POSTED invoices are dispatchable.');
            // TASK-006 prevents creation of a tax invoice until GST policy is
            // resolved. Do not let legacy rows or a UI action bypass that gate.
            if (empty($invoice['tax_policy_code']) || $invoice['tax_policy_code'] === 'PENDING') throw new ValidationException('GST_DEPENDENCY_BLOCKED', 'Dispatch is blocked until GST-policy-valid invoicing is available.');
            if ($this->dispatches->findByInvoiceRef($franchiseRef, $invoiceRef)) throw new ConflictException('DISPATCH_ALREADY_EXISTS', 'Only one full dispatch per invoice is supported by the approved frontend flow.');
            if ($boxes < 1) throw new ValidationException('INVALID_BOXES', 'Boxes must be at least one.');
            $order = $this->orders->findByRefForUpdate($franchiseRef, $invoice['order_ref']);
            if (!$order) throw new ValidationException('ORDER_NOT_FOUND', 'Linked order was not found.');
            OrderStateMachine::assertCanTransition($order['status'], 'DISPATCHED');
            // Reservation remains intact through invoicing and is consumed once,
            // inside this dispatch transaction, using the original FEFO allocation.
            $this->fefo->consumeOrderStock($orgRef, $franchiseRef, $invoice['order_ref'], $actorRef);
            $ref = RefGenerator::generate('dsp'); $number = $this->sequences->next($franchiseRef, 'DISPATCH', date('Y-m-d'));
            $this->dispatches->create(['dispatch_ref' => $ref, 'dispatch_no' => $number, 'org_ref' => $orgRef, 'franchise_ref' => $franchiseRef, 'invoice_ref' => $invoiceRef, 'transporter_ref' => $transporterRef, 'lr_number' => $lrNumber, 'tracking_url' => $trackingUrl, 'dispatch_date' => date('Y-m-d'), 'boxes' => $boxes, 'status' => 'DISPATCHED', 'remarks' => $remarks, 'created_by_ref' => $actorRef]);
            if (!$this->orders->updateStatusNoTransaction($franchiseRef, $invoice['order_ref'], $order['status'], 'DISPATCHED', $actorRef, "Dispatch {$number}")) throw new ConflictException('ORDER_STATE_CHANGED', 'Order changed concurrently.');
            $this->db->insert('dispatch_status_history', ['org_ref' => $orgRef, 'franchise_ref' => $franchiseRef, 'dispatch_ref' => $ref, 'from_status' => null, 'to_status' => 'DISPATCHED', 'actor_ref' => $actorRef, 'reason' => $remarks]);
            return ['dispatch_ref' => $ref, 'dispatch_no' => $number, 'status' => 'DISPATCHED'];
        });
    }

    public function deliver(string $orgRef, string $franchiseRef, string $dispatchRef, string $actorRef, ?string $remarks): array
    {
        return $this->db->transaction(function () use ($orgRef, $franchiseRef, $dispatchRef, $actorRef, $remarks): array {
            $dispatch = $this->dispatches->findByRefForUpdate($franchiseRef, $dispatchRef);
            if (!$dispatch) throw new ValidationException('DISPATCH_NOT_FOUND', 'Dispatch not found.');
            if ($dispatch['status'] === 'DELIVERED') throw new ConflictException('DELIVERY_ALREADY_CONFIRMED', 'Delivery is already confirmed.');
            if (!in_array($dispatch['status'], ['DISPATCHED','IN_TRANSIT'], true)) throw new ValidationException('INVALID_DELIVERY_STATE', 'Only dispatched consignments can be delivered.');
            $order = $this->orders->findByRefForUpdate($franchiseRef, $dispatch['order_ref']);
            if (!$order) throw new ValidationException('ORDER_NOT_FOUND', 'Linked order was not found.');
            OrderStateMachine::assertCanTransition($order['status'], 'DELIVERED');
            if (!$this->dispatches->updateStatusIfCurrent($franchiseRef, $dispatchRef, $dispatch['status'], 'DELIVERED', $remarks)) throw new ConflictException('DISPATCH_STATE_CHANGED', 'Dispatch changed concurrently.');
            if (!$this->orders->updateStatusNoTransaction($franchiseRef, $dispatch['order_ref'], $order['status'], 'DELIVERED', $actorRef, $remarks ?? 'Delivery confirmed')) throw new ConflictException('ORDER_STATE_CHANGED', 'Order changed concurrently.');
            
            // Consume the stock reservations now that delivery is successful
            $this->fefo->consumeOrderStock($orgRef, $franchiseRef, $dispatch['order_ref'], $actorRef);

            $this->db->insert('dispatch_status_history', ['org_ref' => $orgRef, 'franchise_ref' => $franchiseRef, 'dispatch_ref' => $dispatchRef, 'from_status' => $dispatch['status'], 'to_status' => 'DELIVERED', 'actor_ref' => $actorRef, 'reason' => $remarks]);
            return ['dispatch_ref' => $dispatchRef, 'status' => 'DELIVERED'];
        });
    }
}