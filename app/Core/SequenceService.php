<?php
declare(strict_types=1);
namespace App\Core;

final class SequenceService
{
    public function __construct(private \PDO $pdo) {}

    /**
     * Atomically increment the counter and return the formatted sequence number.
     *
     * @param string $orgRef       Organization ref
     * @param string $franchiseRef Franchise ref
     * @param string $key          Counter key: ORDER|INVOICE|DISPATCH|PAYMENT|PARTY|LEAD
     * @param string $period       Period: '2026' or 'FY2627'
     * @param string $prefix       Display prefix: 'ORD', 'INV', 'DSP', 'PAY'
     * @param int    $pad          Zero-padding width (default 6)
     * @return string              e.g. INV/2026/000123
     */
    public function next(
        string $orgRefOrFranchise,
        string $franchiseOrKey,
        ?string $keyOrPeriod = null,
        ?string $period = null,
        ?string $prefix = null,
        int    $pad = 6
    ): string {
        // Support both:
        // next(orgRef, franchiseRef, key, period, prefix, pad)
        // next(franchiseRef, key, period, prefix, pad)
        if ($period === null && $prefix === null && !in_array($keyOrPeriod, ['ORDER','INVOICE','DISPATCH','PAYMENT','PARTY','LEAD'], true)) {
            // Called as next($franchiseRef, $key, $periodOrDate)
            $franchiseRef = $orgRefOrFranchise;
            $key = $franchiseOrKey;
            $period = $keyOrPeriod ? (strlen($keyOrPeriod) === 4 ? $keyOrPeriod : substr($keyOrPeriod, 0, 4)) : self::currentYearPeriod();
            $prefix = match($key) {
                'ORDER'    => 'ORD',
                'INVOICE'  => 'INV',
                'DISPATCH' => 'DSP',
                'PAYMENT'  => 'PAY',
                'PARTY'    => 'PTY',
                'LEAD'     => 'LED',
                default    => $key,
            };
            $orgRef = (string)$this->pdo->query("SELECT org_ref FROM franchises WHERE franchise_ref = " . $this->pdo->quote($franchiseRef) . " LIMIT 1")->fetchColumn() ?: 'ORG-PLATFORM0000000001';
        } else {
            $orgRef = $orgRefOrFranchise;
            $franchiseRef = $franchiseOrKey;
            $key = $keyOrPeriod ?? 'ORDER';
            $period = $period ?? self::currentYearPeriod();
            $prefix = $prefix ?? match($key) {
                'ORDER'    => 'ORD',
                'INVOICE'  => 'INV',
                'DISPATCH' => 'DSP',
                'PAYMENT'  => 'PAY',
                'PARTY'    => 'PTY',
                'LEAD'     => 'LED',
                default    => $key,
            };
        }

        // Atomic: increment counter in one statement, no locking needed
        $stmt = $this->pdo->prepare(
            "INSERT INTO sequence_counters
                 (org_ref, franchise_ref, counter_key, period_key, last_value)
             VALUES
                 (:o, :f, :k, :p, LAST_INSERT_ID(1))
             ON DUPLICATE KEY UPDATE
                 last_value = LAST_INSERT_ID(last_value + 1)"
        );

        $stmt->execute([':o' => $orgRef, ':f' => $franchiseRef, ':k' => $key, ':p' => $period]);

        $n = (int) $this->pdo->lastInsertId();

        return sprintf('%s/%s/%0' . $pad . 'd', $prefix, $period, $n);
    }

    /**
     * Atomically increment and return integer counter value.
     */
    public function nextNumber(string $orgRef, string $franchiseRef, string $key, ?string $period = null): int
    {
        $period = $period ?? self::currentYearPeriod();
        $stmt = $this->pdo->prepare(
            "INSERT INTO sequence_counters
                 (org_ref, franchise_ref, counter_key, period_key, last_value)
             VALUES
                 (:o, :f, :k, :p, LAST_INSERT_ID(1))
             ON DUPLICATE KEY UPDATE
                 last_value = LAST_INSERT_ID(last_value + 1)"
        );

        $stmt->execute([':o' => $orgRef, ':f' => $franchiseRef, ':k' => $key, ':p' => $period]);
        return (int)$this->pdo->lastInsertId();
    }

    /**
     * Compute current financial year period string.
     * Example: April 2026 → 'FY2627'. January 2026 → 'FY2526'.
     */
    public static function currentFyPeriod(int $fyStartMonth = 4): string
    {
        $month = (int) date('n');
        $year  = (int) date('Y');

        if ($month >= $fyStartMonth) {
            return 'FY' . substr((string)$year, 2) . substr((string)($year + 1), 2);
        } else {
            return 'FY' . substr((string)($year - 1), 2) . substr((string)$year, 2);
        }
    }

    /**
     * Calendar year period.
     */
    public static function currentYearPeriod(): string
    {
        return date('Y');
    }
}
