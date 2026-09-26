<?php
declare(strict_types=1);
namespace App\Domain\Payments;

use App\Core\{Database, RefGenerator, TenantContext};
use App\Domain\Authorization\PartyScopePredicate;
use App\Core\Exceptions\{ConflictException, ValidationException};
use App\Support\Money;

/** Manual allocation only: no FIFO/automatic policy is assumed. */
final class AllocationService
{
    public function __construct(private Database $db, private PartyScopePredicate $scope) {}
    public function allocate(TenantContext $ctx, string $paymentRef, string $invoiceRef, string $amount, string $actorRef): array
    {
        $paise = Money::fromDecimal($amount); if ($paise <= 0) throw new ValidationException('INVALID_ALLOCATION_AMOUNT', 'Allocation amount must be greater than zero.');
        $franchiseRef = $ctx->requireFranchise();
        return $this->db->transaction(function () use ($ctx, $franchiseRef, $paymentRef, $invoiceRef, $paise, $actorRef): array {
            $params = [':f' => $franchiseRef, ':payment' => $paymentRef];
            $scope = $this->scope->clause($ctx, 'party', $params, 'payments');
            $payment = $this->db->fetchOne("SELECT pay.* FROM payments pay JOIN parties party ON party.franchise_ref=pay.franchise_ref AND party.party_ref=pay.party_ref WHERE pay.franchise_ref=:f AND pay.payment_ref=:payment AND $scope LIMIT 1 FOR UPDATE", $params);
            if (!$payment) throw new ValidationException('ALLOCATION_REFERENCE_NOT_FOUND', 'Payment or invoice was not found.');
            $invoice = $this->db->fetchOne('SELECT * FROM invoices WHERE franchise_ref = ? AND invoice_ref = ? AND party_ref = ? LIMIT 1 FOR UPDATE', [$franchiseRef, $invoiceRef, $payment['party_ref']]);
            if (!$invoice) throw new ValidationException('ALLOCATION_REFERENCE_NOT_FOUND', 'Payment or invoice was not found.');
            if ($payment['party_ref'] !== $invoice['party_ref']) throw new ValidationException('CROSS_PARTY_ALLOCATION', 'Payment and invoice must belong to the same party.');
            if (in_array($payment['status'], ['REVERSED','CANCELLED'], true) || $invoice['status'] !== 'POSTED') throw new ValidationException('INVALID_ALLOCATION_STATE', 'Payment or invoice is not allocatable.');
            $available = Money::fromDecimal($payment['amount']) - Money::fromDecimal($payment['allocated_amount']);
            $outstanding = Money::fromDecimal($invoice['grand_total']) - Money::fromDecimal($invoice['paid_total']);
            if ($paise > $available) throw new ConflictException('PAYMENT_OVER_ALLOCATION', 'Allocation exceeds unallocated payment balance.');
            if ($paise > $outstanding) throw new ConflictException('INVOICE_OVER_ALLOCATION', 'Allocation exceeds invoice outstanding balance.');
            $ref = RefGenerator::generate('pal'); $amountDecimal = Money::toDecimal($paise);
            $this->db->insert('payment_allocations', ['allocation_ref' => $ref, 'org_ref' => $payment['org_ref'], 'franchise_ref' => $franchiseRef, 'payment_ref' => $paymentRef, 'invoice_ref' => $invoiceRef, 'allocated_amount' => $amountDecimal, 'status' => 'ACTIVE', 'created_by_ref' => $actorRef]);
            $this->db->prepare("UPDATE payments SET allocated_amount = allocated_amount + ?, status = CASE WHEN allocated_amount + ? >= amount THEN 'ALLOCATED' ELSE 'PARTIALLY_ALLOCATED' END WHERE franchise_ref = ? AND payment_ref = ?")->execute([$amountDecimal, $amountDecimal, $franchiseRef, $paymentRef]);
            $this->db->prepare('UPDATE invoices SET paid_total = paid_total + ? WHERE franchise_ref = ? AND invoice_ref = ?')->execute([$amountDecimal, $franchiseRef, $invoiceRef]);
            return ['allocation_ref' => $ref, 'payment_ref' => $paymentRef, 'invoice_ref' => $invoiceRef, 'allocated_amount' => $amountDecimal];
        });
    }
}
