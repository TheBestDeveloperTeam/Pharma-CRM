<?php
declare(strict_types=1);
namespace App\Domain\Parties;

use App\Repositories\Contracts\PartyRepositoryInterface;

/** Credit facts only. It does not choose block/warn/approval behavior for Orders. */
final class PartyCreditService
{
    public function __construct(private PartyRepositoryInterface $parties) {}

    public function check(string $franchiseRef, string $partyRef, float $proposedExposure = 0.0): array
    {
        $summary = $this->parties->getLedgerSummary($franchiseRef, $partyRef);
        $limit = (float)($summary['credit_limit'] ?? 0);
        $outstanding = (float)($summary['current_outstanding'] ?? 0);
        $available = $limit > 0 ? $limit - $outstanding : null;
        return array_merge($summary, [
            'proposed_exposure' => number_format($proposedExposure, 2, '.', ''),
            'available_credit' => $available === null ? null : number_format($available, 2, '.', ''),
            'credit_breached' => $limit > 0 && ($outstanding + $proposedExposure) > $limit,
        ]);
    }
}
