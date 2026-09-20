<?php
declare(strict_types=1);
namespace App\Domain\Dispatch;

use App\Core\Database;
use App\Core\RefGenerator;
use App\Core\SequenceService;
use App\Core\Exceptions\ValidationException;
use App\Repositories\Contracts\DispatchRepositoryInterface;
use App\Repositories\Contracts\InvoiceRepositoryInterface;
use App\Repositories\Contracts\OrderRepositoryInterface;

final class DispatchService
{
    public function __construct(
        private Database $db,
        private DispatchRepositoryInterface $dispatchRepo,
        private InvoiceRepositoryInterface $invoiceRepo,
        private OrderRepositoryInterface $orderRepo,
        private SequenceService $sequenceService,
    ) {}

    public function createDispatch(
        string $orgRef,
        string $franchiseRef,
        string $invoiceRef,
        ?string $transporterRef,
        ?string $lrNumber,
        ?string $trackingUrl,
        int $boxes,
        ?string $remarks,
        string $actorRef
    ): array {
        $invoice = $this->invoiceRepo->findByRef($franchiseRef, $invoiceRef);
        if (!$invoice || $invoice['status'] !== 'POSTED') {
            throw new ValidationException('INVALID_INVOICE', 'Invoice not found or not in POSTED status.');
        }

        $existing = $this->dispatchRepo->findByInvoiceRef($franchiseRef, $invoiceRef);
        if ($existing) {
            throw new ValidationException('DISPATCH_EXISTS', 'Dispatch record already exists for this invoice.');
        }

        $dispatchRef = RefGenerator::generate('dsp');

        return $this->db->transaction(function() use (
            $orgRef, $franchiseRef, $invoiceRef, $invoice, $transporterRef,
            $lrNumber, $trackingUrl, $boxes, $remarks, $actorRef, $dispatchRef
        ) {
            $dispatchNo = $this->sequenceService->next($franchiseRef, 'DISPATCH', date('Y-m-d'));

            $this->dispatchRepo->create([
                'dispatch_ref'    => $dispatchRef,
                'dispatch_no'     => $dispatchNo,
                'org_ref'         => $orgRef,
                'franchise_ref'   => $franchiseRef,
                'invoice_ref'     => $invoiceRef,
                'transporter_ref' => $transporterRef,
                'lr_number'       => $lrNumber,
                'tracking_url'    => $trackingUrl,
                'dispatch_date'   => date('Y-m-d'),
                'boxes'           => max(1, $boxes),
                'status'          => 'DISPATCHED',
                'remarks'         => $remarks,
                'created_by_ref'  => $actorRef,
            ]);

            // Update order status to DISPATCHED
            $this->orderRepo->updateStatus(
                $franchiseRef,
                $invoice['order_ref'],
                'DISPATCHED',
                $actorRef,
                "Dispatched via LR #{$lrNumber}"
            );

            return [
                'dispatch_ref' => $dispatchRef,
                'dispatch_no'  => $dispatchNo,
                'status'       => 'DISPATCHED',
                'lr_number'    => $lrNumber,
            ];
        });
    }

    public function markDelivered(string $franchiseRef, string $dispatchRef, string $actorRef): bool
    {
        $dispatch = $this->dispatchRepo->findByRef($franchiseRef, $dispatchRef);
        if (!$dispatch) {
            throw new ValidationException('DISPATCH_NOT_FOUND', 'Dispatch not found.');
        }

        return $this->db->transaction(function() use ($franchiseRef, $dispatch, $dispatchRef, $actorRef) {
            $this->dispatchRepo->updateStatus($franchiseRef, $dispatchRef, 'DELIVERED');

            $invoice = $this->invoiceRepo->findByRef($franchiseRef, $dispatch['invoice_ref']);
            if ($invoice) {
                $this->orderRepo->updateStatus(
                    $franchiseRef,
                    $invoice['order_ref'],
                    'DELIVERED',
                    $actorRef,
                    'Consignment delivered to party'
                );
            }

            return true;
        });
    }
}
