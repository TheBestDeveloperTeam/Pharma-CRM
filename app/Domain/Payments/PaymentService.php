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
        private AllocationService $allocationService
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
                $result = $this->allocationService->autoAllocateFifo($franchiseRef, $paymentRef, $actorRef);
                $allocations = $result['allocations'] ?? [];
            }

            return [
                'payment_ref' => $paymentRef,
                'payment_no'  => $paymentNo,
                'amount'      => $amount,
                'allocations' => $allocations,
            ];
        });
    }

    public function bouncePayment(string $franchiseRef, string $paymentRef, string $actorRef): array
    {
        return $this->db->transaction(function() use ($franchiseRef, $paymentRef, $actorRef) {
            $this->allocationService->reverse($franchiseRef, $paymentRef, $actorRef);
            $this->db->prepare("UPDATE payments SET status = 'BOUNCED', updated_at = NOW() WHERE franchise_ref = ? AND payment_ref = ?")->execute([$franchiseRef, $paymentRef]);
            return ['payment_ref' => $paymentRef, 'status' => 'BOUNCED'];
        });
    }
}
