<?php
declare(strict_types=1);
namespace App\Domain\Leads;

use App\Core\RefGenerator;
use App\Core\Exceptions\ConflictException;
use App\Core\Exceptions\NotFoundException;
use App\Repositories\Contracts\LeadRepositoryInterface;
use App\Repositories\Contracts\SystemSettingsRepositoryInterface;

final class LeadService
{
    public function __construct(
        private LeadRepositoryInterface $leads,
        private SystemSettingsRepositoryInterface $settings,
        private LeadAssignmentService $assignment,
        private LeadStateMachine $stateMachine,
    ) {}

    /**
     * Create lead with mobile normalization, dup policy check, auto-assignment, and activity logging
     */
    public function create(array $data): string
    {
        $franchiseRef = $data['franchise_ref'];
        $orgRef       = $data['org_ref'];
        $rawMobile    = $data['mobile'] ?? '';
        $normMobile   = MobileNormalizer::normalize($rawMobile);
        $data['mobile_norm'] = $normMobile;

        // Check business mobile duplicate policy if manual lead
        if (empty($data['external_lead_id'])) {
            $existing = $this->leads->findByMobile($franchiseRef, $normMobile);
            if ($existing) {
                $policy = $this->settings->get($franchiseRef, 'lead_dup_policy') ?? 'CREATE_ANYWAY';
                if ($policy === 'REJECT') {
                    throw new ConflictException('LEAD_DUPLICATE_MOBILE', 'A lead with this mobile number already exists.');
                }
                if ($policy === 'LINK') {
                    // Record activity on existing lead and return existing ref
                    $this->leads->addActivity($franchiseRef, [
                        'activity_ref'  => RefGenerator::generate('ACT'),
                        'org_ref'       => $orgRef,
                        'lead_ref'      => $existing['lead_ref'],
                        'user_ref'      => $data['created_by_ref'],
                        'activity_type' => 'DUPLICATE_ATTEMPT',
                        'activity_note' => 'Duplicate lead intake linked. Contact: ' . ($data['contact_name'] ?? ''),
                    ]);
                    return $existing['lead_ref'];
                }
            }
        }

        // Auto-assign round-robin if not pre-assigned
        if (empty($data['assigned_user_ref'])) {
            $assignedUser = $this->assignment->assignNext($orgRef, $franchiseRef);
            if ($assignedUser) {
                $data['assigned_user_ref'] = $assignedUser;
                $data['status'] = 'ASSIGNED';
            }
        }

        $leadRef = $data['lead_ref'] ?? RefGenerator::generate('LED');
        $data['lead_ref'] = $leadRef;

        $createdRef = $this->leads->create($data);

        // Add creation activity
        $this->leads->addActivity($franchiseRef, [
            'activity_ref'  => RefGenerator::generate('ACT'),
            'org_ref'       => $orgRef,
            'lead_ref'      => $createdRef,
            'user_ref'      => $data['created_by_ref'],
            'activity_type' => 'CREATE',
            'to_status'     => $data['status'] ?? 'NEW',
            'activity_note' => 'Lead created' . (!empty($data['assigned_user_ref']) ? " and assigned to {$data['assigned_user_ref']}" : ''),
        ]);

        return $createdRef;
    }

    /**
     * Transition lead status with state machine enforcement and activity audit
     */
    public function changeStatus(string $franchiseRef, string $leadRef, string $newStatus, string $userRef, ?string $note = null, ?string $partyRef = null): bool
    {
        $lead = $this->leads->findByRef($franchiseRef, $leadRef);
        if (!$lead) {
            throw new NotFoundException('LEAD_NOT_FOUND', 'Lead not found.');
        }

        $this->stateMachine->assertTransition($lead['status'], $newStatus);

        $ok = $this->leads->updateStatus($franchiseRef, $leadRef, $newStatus, $partyRef);
        if ($ok) {
            $this->leads->addActivity($franchiseRef, [
                'activity_ref'  => RefGenerator::generate('ACT'),
                'org_ref'       => $lead['org_ref'],
                'lead_ref'      => $leadRef,
                'user_ref'      => $userRef,
                'activity_type' => 'STATUS_CHANGE',
                'from_status'   => $lead['status'],
                'to_status'     => $newStatus,
                'activity_note' => $note,
            ]);
        }

        return $ok;
    }

    /**
     * Reassign lead
     */
    public function reassign(string $franchiseRef, string $leadRef, string $newAssigneeRef, string $actorRef, ?string $reason = null): bool
    {
        $lead = $this->leads->findByRef($franchiseRef, $leadRef);
        if (!$lead) {
            throw new NotFoundException('LEAD_NOT_FOUND', 'Lead not found.');
        }

        $fromAssignee = $lead['assigned_user_ref'];
        $ok = $this->leads->assign($franchiseRef, $leadRef, $newAssigneeRef);

        if ($ok) {
            $this->leads->addActivity($franchiseRef, [
                'activity_ref'  => RefGenerator::generate('ACT'),
                'org_ref'       => $lead['org_ref'],
                'lead_ref'      => $leadRef,
                'user_ref'      => $actorRef,
                'activity_type' => 'REASSIGN',
                'from_status'   => $lead['status'],
                'to_status'     => 'ASSIGNED',
                'activity_note' => "Reassigned from " . ($fromAssignee ?? 'NONE') . " to {$newAssigneeRef}. Reason: " . ($reason ?? 'N/A'),
            ]);
        }

        return $ok;
    }
}
