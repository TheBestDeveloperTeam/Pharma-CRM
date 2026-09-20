<?php
declare(strict_types=1);
namespace App\Domain\Orders;

use App\Core\Database;
use App\Support\Money;

final class CreditRuleService
{
    public function __construct(private Database $db) {}

    /**
     * Calculates party outstanding exposure in paise:
     * exposure = opening_outstanding + SUM(posted_invoices.grand_total - paid_total) - unallocated_payments
     */
    public function getPartyOutstanding(string $franchiseRef, string $partyRef): int
    {
        $party = $this->db->fetchOne(
            "SELECT opening_outstanding FROM parties WHERE franchise_ref = :f AND party_ref = :p LIMIT 1",
            [':f' => $franchiseRef, ':p' => $partyRef]
        );

        if (!$party) {
            return 0;
        }

        $opening = Money::fromDecimal((string)$party['opening_outstanding']);

        // Invoiced but unpaid balance
        $invRow = $this->db->fetchOne(
            "SELECT COALESCE(SUM(grand_total - paid_total), 0) as unpaid_inv
             FROM invoices
             WHERE franchise_ref = :f AND party_ref = :p AND status = 'POSTED'",
            [':f' => $franchiseRef, ':p' => $partyRef]
        );
        $unpaidInv = Money::fromDecimal((string)($invRow['unpaid_inv'] ?? '0'));

        // Unallocated payments balance
        $payRow = $this->db->fetchOne(
            "SELECT COALESCE(SUM(amount - allocated_amount), 0) as unallocated
             FROM payments
             WHERE franchise_ref = :f AND party_ref = :p AND status IN ('RECORDED', 'PARTIALLY_ALLOCATED')",
            [':f' => $franchiseRef, ':p' => $partyRef]
        );
        $unallocatedPay = Money::fromDecimal((string)($payRow['unallocated'] ?? '0'));

        $totalOutstanding = $opening + $unpaidInv - $unallocatedPay;
        return max(0, $totalOutstanding);
    }

    /**
     * Validates if adding orderAmount would violate credit limit.
     * Returns ['allowed' => bool, 'reason' => ?string, 'action' => 'ALLOW'|'HOLD'|'BLOCK']
     */
    public function checkCredit(string $franchiseRef, string $partyRef, int $orderAmountPaise): array
    {
        $party = $this->db->fetchOne(
            "SELECT credit_limit FROM parties WHERE franchise_ref = :f AND party_ref = :p LIMIT 1",
            [':f' => $franchiseRef, ':p' => $partyRef]
        );

        if (!$party) {
            return ['allowed' => true, 'reason' => null, 'action' => 'ALLOW'];
        }

        $creditLimitPaise = Money::fromDecimal((string)$party['credit_limit']);
        if ($creditLimitPaise <= 0) {
            // 0 limit means no credit limit restriction or strictly cash
            return ['allowed' => true, 'reason' => null, 'action' => 'ALLOW'];
        }

        $currentOutstanding = $this->getPartyOutstanding($franchiseRef, $partyRef);
        $projectedTotal = $currentOutstanding + $orderAmountPaise;

        if ($projectedTotal > $creditLimitPaise) {
            $excess = Money::format($projectedTotal - $creditLimitPaise);
            return [
                'allowed' => false,
                'reason'  => "Credit limit exceeded by ₹{$excess}.",
                'action'  => 'HOLD',
            ];
        }

        return ['allowed' => true, 'reason' => null, 'action' => 'ALLOW'];
    }
}
