<?php
declare(strict_types=1);
namespace App\Domain\Payments;

use App\Core\Database;
use App\Core\RefGenerator;
use App\Core\SequenceService;
use App\Core\Exceptions\ValidationException;
use App\Support\Money;
use App\Repositories\Contracts\PaymentRepositoryInterface;
use App\Repositories\Contracts\PartyRepositoryInterface;

final class PaymentService
{
    public function __construct(
        private Database $db,
        private PaymentRepositoryInterface $paymentRepo,
        private PartyRepositoryInterface $partyRepo,
        private SequenceService $sequenceService,
    ) {}

    public function recordPayment(
        string $orgRef,
        string $franchiseRef,
        string $partyRef,
        string $paymentDate,
        float $amount,
        string $mode,
        ?string $referenceNo,
        ?string $remarks,
        bool $autoAllocate,
        string $actorRef
    ): array {
        if ($amount <= 0) {
            throw new ValidationException('INVALID_AMOUNT', 'Payment amount must be greater than zero.');
        }

        $party = $this->partyRepo->findByRef($franchiseRef, $partyRef);
        if (!$party) {
            throw new ValidationException('PARTY_NOT_FOUND', 'Party not found.');
        }

        $paymentRef = RefGenerator::generate('pay');

        return $this->db->transaction(function() use (
            $orgRef, $franchiseRef, $partyRef, $paymentDate, $amount, $mode,
            $referenceNo, $remarks, $autoAllocate, $actorRef, $paymentRef
        ) {
            $paymentNo = $this->sequenceService->next($franchiseRef, 'PAYMENT', $paymentDate);

            $this->paymentRepo->create([
                'payment_ref'      => $paymentRef,
                'payment_no'       => $paymentNo,
                'org_ref'          => $orgRef,
                'franchise_ref'    => $franchiseRef,
                'party_ref'        => $partyRef,
                'payment_date'     => $paymentDate,
                'amount'           => $amount,
                'allocated_amount' => 0.00,
                'mode'             => $mode,
                'reference_no'     => $referenceNo,
                'remarks'          => $remarks,
                'status'           => 'RECORDED',
                'created_by_ref'   => $actorRef,
            ]);

            $allocations = [];
            if ($autoAllocate) {
                $allocations = $this->autoAllocatePayment($franchiseRef, $paymentRef, $partyRef, $amount);
            }

            return [
                'payment_ref' => $paymentRef,
                'payment_no'  => $paymentNo,
                'amount'      => $amount,
                'allocations' => $allocations,
            ];
        });
    }

    private function autoAllocatePayment(string $franchiseRef, string $paymentRef, string $partyRef, float $amount): array
    {
        $sqlRepo = $this->paymentRepo;
        if (!method_exists($sqlRepo, 'getOpenInvoicesForParty')) {
            return [];
        }

        $openInvoices = $sqlRepo->getOpenInvoicesForParty($franchiseRef, $partyRef);
        $remainingPaise = Money::fromDecimal((string)$amount);
        $allocations = [];

        foreach ($openInvoices as $inv) {
            if ($remainingPaise <= 0) {
                break;
            }

            $unpaidPaise = Money::fromDecimal((string)$inv['grand_total']) - Money::fromDecimal((string)$inv['paid_total']);
            if ($unpaidPaise <= 0) {
                continue;
            }

            $allocPaise = min($remainingPaise, $unpaidPaise);
            $allocFloat = (float)Money::toDecimal($allocPaise);

            $allocRef = $this->paymentRepo->allocate($franchiseRef, $paymentRef, $inv['invoice_ref'], $allocFloat);

            $allocations[] = [
                'allocation_ref'   => $allocRef,
                'invoice_ref'      => $inv['invoice_ref'],
                'invoice_no'       => $inv['invoice_no'],
                'allocated_amount' => $allocFloat,
            ];

            $remainingPaise -= $allocPaise;
        }

        return $allocations;
    }
}
