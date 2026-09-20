<?php
declare(strict_types=1);
namespace App\Domain\Leads;

use App\Core\Exceptions\BusinessRuleException;

final class LeadStateMachine
{
    private const TRANSITIONS = [
        'NEW'                => ['ASSIGNED', 'CONTACTED', 'LOST', 'REJECTED'],
        'ASSIGNED'           => ['CONTACTED', 'LOST', 'REJECTED'],
        'CONTACTED'          => ['INTERESTED', 'FOLLOW_UP', 'LOST', 'REJECTED'],
        'INTERESTED'         => ['FOLLOW_UP', 'DOCUMENTS_PENDING', 'LOST', 'REJECTED'],
        'FOLLOW_UP'          => ['INTERESTED', 'DOCUMENTS_PENDING', 'CONTACTED', 'LOST', 'REJECTED'],
        'DOCUMENTS_PENDING'  => ['QUALIFIED', 'FOLLOW_UP', 'LOST', 'REJECTED'],
        'QUALIFIED'          => ['CONVERTED', 'LOST', 'REJECTED'],
        'LOST'               => ['ARCHIVED'],
        'REJECTED'           => ['ARCHIVED'],
        'CONVERTED'          => [],  // Terminal
        'ARCHIVED'           => [],  // Terminal
    ];

    public function canTransition(string $from, string $to): bool
    {
        return in_array($to, self::TRANSITIONS[$from] ?? [], true);
    }

    public function assertTransition(string $from, string $to): void
    {
        if (!$this->canTransition($from, $to)) {
            throw new BusinessRuleException(
                'INVALID_LEAD_TRANSITION',
                "Cannot transition lead from [{$from}] to [{$to}]."
            );
        }
    }
}
