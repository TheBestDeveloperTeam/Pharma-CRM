<?php
return [
    'name'  => 'agent-leads',
    'scope' => 'leads',
    'group' => 'crm',
    'steps' => [
        // 1. Mobile Normalization verification
        function() {
            $raw = '+91 98765-43210';
            $norm = \App\Domain\Leads\MobileNormalizer::normalize($raw);
            \Tests\Support\Assert::equals('9876543210', $norm, 'Standard 10-digit normalized Indian mobile');

            $raw2 = '09876543210';
            $norm2 = \App\Domain\Leads\MobileNormalizer::normalize($raw2);
            \Tests\Support\Assert::equals('9876543210', $norm2, 'Stripped leading zero');
        },
        // 2. Create lead with auto-assignment & activity trail
        function() {
            $frnRef = 'FRN-MUMBAI000000000001';
            $orgRef = 'ORG-PLATFORM0000000001';
            $leadService = \App\Core\Container::getInstance()->make(\App\Domain\Leads\LeadService::class);
            $leadRepo = \App\Core\Container::getInstance()->make(\App\Repositories\Contracts\LeadRepositoryInterface::class);

            $leadRef = $leadService->create([
                'org_ref'        => $orgRef,
                'franchise_ref'  => $frnRef,
                'contact_name'   => 'Dr. Rajesh Mehta',
                'firm_name'      => 'Mehta Clinic & Medicos',
                'mobile'         => '9820011223',
                'created_by_ref' => 'USR-FRNADMIN000000001',
            ]);

            $lead = $leadRepo->findByRef($frnRef, $leadRef);
            \Tests\Support\Assert::true($lead !== null, 'Lead created in DB');
            \Tests\Support\Assert::equals('9820011223', $lead['mobile_norm'], 'Mobile normalized in DB');

            $acts = $leadRepo->getActivities($frnRef, $leadRef);
            \Tests\Support\Assert::true(count($acts) >= 1, 'At least 1 activity recorded on creation');
            \Tests\Support\Assert::equals('CREATE', $acts[0]['activity_type'], 'Activity type is CREATE');
        },
        // 3. Duplicate intake policy check (LINK vs REJECT)
        function() {
            $frnRef = 'FRN-MUMBAI000000000001';
            $orgRef = 'ORG-PLATFORM0000000001';
            $leadService = \App\Core\Container::getInstance()->make(\App\Domain\Leads\LeadService::class);
            $settings = \App\Core\Container::getInstance()->make(\App\Repositories\Contracts\SystemSettingsRepositoryInterface::class);

            // Set policy to REJECT
            $settings->set($orgRef, $frnRef, 'lead_dup_policy', 'REJECT', 'USR-FRNADMIN000000001');

            \Tests\Support\Assert::throws(
                fn() => $leadService->create([
                    'org_ref'        => $orgRef,
                    'franchise_ref'  => $frnRef,
                    'contact_name'   => 'Dr. Rajesh Dup',
                    'mobile'         => '+91 98200-11223', // Same mobile as step 2
                    'created_by_ref' => 'USR-FRNADMIN000000001',
                ]),
                \App\Core\Exceptions\ConflictException::class,
                'Duplicate mobile with REJECT policy throws ConflictException'
            );

            // Set policy to LINK
            $settings->set($orgRef, $frnRef, 'lead_dup_policy', 'LINK', 'USR-FRNADMIN000000001');
            $ref = $leadService->create([
                'org_ref'        => $orgRef,
                'franchise_ref'  => $frnRef,
                'contact_name'   => 'Dr. Rajesh Linked',
                'mobile'         => '9820011223',
                'created_by_ref' => 'USR-FRNADMIN000000001',
            ]);
            \Tests\Support\Assert::true(!empty($ref), 'LINK policy returns existing lead reference');
        },
        // 4. Lead State Machine transitions
        function() {
            $sm = new \App\Domain\Leads\LeadStateMachine();
            \Tests\Support\Assert::true($sm->canTransition('NEW', 'ASSIGNED'), 'NEW -> ASSIGNED allowed');
            \Tests\Support\Assert::true($sm->canTransition('ASSIGNED', 'CONTACTED'), 'ASSIGNED -> CONTACTED allowed');
            \Tests\Support\Assert::true($sm->canTransition('QUALIFIED', 'CONVERTED'), 'QUALIFIED -> CONVERTED allowed');
            \Tests\Support\Assert::true(!$sm->canTransition('CONVERTED', 'NEW'), 'CONVERTED terminal state cannot transition');
            \Tests\Support\Assert::true(!$sm->canTransition('NEW', 'CONVERTED'), 'NEW cannot directly transition to CONVERTED');
        },
    ]
];
