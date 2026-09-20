<?php
return [
    'name'  => 'agent-followups',
    'scope' => 'followups',
    'group' => 'crm',
    'steps' => [
        // 1. Create follow-up with next action and schedule
        function() {
            $frnRef = 'FRN-MUMBAI000000000001';
            $orgRef = 'ORG-PLATFORM0000000001';
            $fuService = \App\Core\Container::getInstance()->make(\App\Domain\FollowUps\FollowUpService::class);
            $fuRepo = \App\Core\Container::getInstance()->make(\App\Repositories\Contracts\FollowUpRepositoryInterface::class);
            $leadRepo = \App\Core\Container::getInstance()->make(\App\Repositories\Contracts\LeadRepositoryInterface::class);

            $lead = $leadRepo->list($frnRef, [], 1, 1)['items'][0] ?? null;
            $leadRef = $lead ? $lead['lead_ref'] : 'LED-TEST00000000001';

            $fuRef = $fuService->create([
                'org_ref'           => $orgRef,
                'franchise_ref'     => $frnRef,
                'lead_ref'          => $leadRef,
                'assigned_user_ref' => 'USR-SALES00000000001',
                'activity_type'     => 'CALL',
                'next_action'       => 'Call Dr. Sharma regarding pricing proposal',
                'next_follow_up_at' => date('Y-m-d H:i:s', time() + 86400),
                'created_by_ref'    => 'USR-SALES00000000001',
            ]);

            $fu = $fuRepo->findByRef($frnRef, $fuRef);
            \Tests\Support\Assert::true($fu !== null, 'Follow-up created in DB');
            \Tests\Support\Assert::equals('PENDING', $fu['status'], 'Status is PENDING');
        },
        // 2. Complete follow-up with remark
        function() {
            $frnRef = 'FRN-MUMBAI000000000001';
            $fuService = \App\Core\Container::getInstance()->make(\App\Domain\FollowUps\FollowUpService::class);
            $fuRepo = \App\Core\Container::getInstance()->make(\App\Repositories\Contracts\FollowUpRepositoryInterface::class);

            $pending = $fuRepo->list($frnRef, ['status' => 'PENDING'], 1, 1)['items'][0] ?? null;
            \Tests\Support\Assert::true($pending !== null, 'Pending follow-up found');

            $fuService->complete($frnRef, $pending['followup_ref'], 'Doctor agreed to order 50 packs.');
            $updated = $fuRepo->findByRef($frnRef, $pending['followup_ref']);
            \Tests\Support\Assert::equals('COMPLETED', $updated['status'], 'Follow-up marked COMPLETED');
        },
        // 3. Reschedule follow-up
        function() {
            $frnRef = 'FRN-MUMBAI000000000001';
            $orgRef = 'ORG-PLATFORM0000000001';
            $fuService = \App\Core\Container::getInstance()->make(\App\Domain\FollowUps\FollowUpService::class);
            $fuRepo = \App\Core\Container::getInstance()->make(\App\Repositories\Contracts\FollowUpRepositoryInterface::class);
            $leadRepo = \App\Core\Container::getInstance()->make(\App\Repositories\Contracts\LeadRepositoryInterface::class);

            $lead = $leadRepo->list($frnRef, [], 1, 1)['items'][0] ?? null;
            $leadRef = $lead ? $lead['lead_ref'] : 'LED-TEST00000000001';

            $fuRef = $fuService->create([
                'org_ref'           => $orgRef,
                'franchise_ref'     => $frnRef,
                'lead_ref'          => $leadRef,
                'assigned_user_ref' => 'USR-SALES00000000001',
                'activity_type'     => 'VISIT',
                'next_action'       => 'Visit clinic in Dadar',
                'next_follow_up_at' => date('Y-m-d H:i:s', time() + 3600),
                'created_by_ref'    => 'USR-SALES00000000001',
            ]);

            $newDate = date('Y-m-d H:i:s', time() + 172800);
            $fuService->reschedule($frnRef, $fuRef, $newDate, 'Doctor was in surgery');
            $updated = $fuRepo->findByRef($frnRef, $fuRef);

            \Tests\Support\Assert::equals('RESCHEDULED', $updated['status'], 'Follow-up status is RESCHEDULED');
            \Tests\Support\Assert::equals($newDate, $updated['next_follow_up_at'], 'New date updated');
        },
    ]
];
