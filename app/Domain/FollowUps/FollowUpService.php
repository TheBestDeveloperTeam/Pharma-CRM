<?php
declare(strict_types=1);
namespace App\Domain\FollowUps;

use App\Core\RefGenerator;
use App\Core\Exceptions\ValidationException;
use App\Core\Exceptions\NotFoundException;
use App\Repositories\Contracts\FollowUpRepositoryInterface;
use App\Repositories\Contracts\LeadRepositoryInterface;

final class FollowUpService
{
    public function __construct(
        private FollowUpRepositoryInterface $followups,
        private LeadRepositoryInterface $leads,
    ) {}

    public function create(array $data): string
    {
        if (empty($data['next_action'])) {
            throw new ValidationException('NEXT_ACTION_REQUIRED', 'Next action is mandatory for follow-ups.');
        }
        if (empty($data['next_follow_up_at'])) {
            throw new ValidationException('NEXT_FOLLOW_UP_AT_REQUIRED', 'Next follow-up date/time is required.');
        }

        $franchiseRef = $data['franchise_ref'];
        $leadRef = $data['lead_ref'] ?? null;

        if ($leadRef) {
            $lead = $this->leads->findByRef($franchiseRef, $leadRef);
            if (!$lead) {
                throw new NotFoundException('LEAD_NOT_FOUND', 'Target lead not found.');
            }
            // Update next_follow_up_at on lead
            $this->leads->update($franchiseRef, $leadRef, [
                'next_follow_up_at' => $data['next_follow_up_at'],
                'updated_by_ref'    => $data['created_by_ref'],
            ]);
        }

        $data['followup_ref'] = $data['followup_ref'] ?? RefGenerator::generate('FLW');
        return $this->followups->create($data);
    }

    public function complete(string $franchiseRef, string $followupRef, ?string $remark = null): bool
    {
        return $this->followups->update($franchiseRef, $followupRef, [
            'status' => 'COMPLETED',
            'remark' => $remark,
        ]);
    }

    public function reschedule(string $franchiseRef, string $followupRef, string $newDate, ?string $remark = null): bool
    {
        $fu = $this->followups->findByRef($franchiseRef, $followupRef);
        if (!$fu) {
            throw new NotFoundException('FOLLOWUP_NOT_FOUND', 'Follow-up not found.');
        }

        if (!empty($fu['lead_ref'])) {
            $this->leads->update($franchiseRef, $fu['lead_ref'], [
                'next_follow_up_at' => $newDate,
            ]);
        }

        return $this->followups->update($franchiseRef, $followupRef, [
            'status'             => 'RESCHEDULED',
            'next_follow_up_at'  => $newDate,
            'remark'             => $remark ?? $fu['remark'],
        ]);
    }
}
