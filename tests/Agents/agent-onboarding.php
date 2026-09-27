<?php
return [
    'name'  => 'agent-onboarding',
    'scope' => 'onboarding',
    'group' => 'crm',
    'steps' => [
        // 1. Issue onboarding invite link
        function() {
            $frnRef = 'FRN-MUMBAI000000000001';
            $orgRef = 'ORG-PLATFORM0000000001';
            $onboarding = \App\Core\Container::getInstance()->make(\App\Domain\Onboarding\OnboardingService::class);
            $ctx = new \App\Core\TenantContext($orgRef, $frnRef, 'USR-FRNADMIN000000001', 'FRANCHISE_ADMIN', 'FRANCHISE', null, 'test-req-id');
            $invite = $onboarding->issueInvite($ctx, null, 'USR-FRNADMIN000000001');
            \Tests\Support\Assert::true(!empty($invite['token']), 'Raw single-use token provided');
            \Tests\Support\Assert::true(!empty($invite['invite_ref']), 'Invite ref created');

            // Token must be stored hashed in DB
            $db = \App\Core\Container::getInstance()->make(\App\Core\Database::class);
            $tokenHash = hash('sha256', $invite['token']);
            $row = $db->fetchOne("SELECT * FROM onboarding_invites WHERE token_hash = :h", [':h' => $tokenHash]);
            \Tests\Support\Assert::true($row !== null, 'Token hash verified in DB');
        },
        // 2. Complete registration using invite token -> Party + Distributor user created
        function() {
            $frnRef = 'FRN-MUMBAI000000000001';
            $orgRef = 'ORG-PLATFORM0000000001';
            $onboarding = \App\Core\Container::getInstance()->make(\App\Domain\Onboarding\OnboardingService::class);
            $ctx = new \App\Core\TenantContext($orgRef, $frnRef, 'USR-FRNADMIN000000001', 'FRANCHISE_ADMIN', 'FRANCHISE', null, 'test-req-id');
            $invite = $onboarding->issueInvite($ctx, null, 'USR-FRNADMIN000000001');

            $res = $onboarding->register($invite['token'], [
                'firm_name'    => 'New Horizon Meditech',
                'constitution_type' => 'Pvt Ltd',
                'contact_name' => 'Anil Deshmukh',
                'designation'  => 'Director',
                'email'        => 'horizon.' . bin2hex(random_bytes(3)) . '@meditech.local',
                'password'     => 'SecurePass@123',
                'mobile'       => '9833445566',
                'gstin'        => '27AAACA1234A1Z5',
                'drug_license_no' => 'MH-MZ3-123456',
                'drug_license_validity' => '2030-12-31',
                'pan'          => 'AAACA1234A',
                'billing_address' => '101, Test Appt',
                'shipping_address' => '101, Test Appt',
                'pincode'      => '400001',
                'preferred_product_categories' => ['Pharma'],
                'documents' => [],
            ]);

            \Tests\Support\Assert::equals('SUBMITTED', $res['status'], 'Registration submitted');
            \Tests\Support\Assert::true(!empty($res['onboarding_ref']), 'Registration ref returned');
        },
        // 3. Re-using same invite token must fail (single-use)
        function() {
            $frnRef = 'FRN-MUMBAI000000000001';
            $orgRef = 'ORG-PLATFORM0000000001';
            $onboarding = \App\Core\Container::getInstance()->make(\App\Domain\Onboarding\OnboardingService::class);
            $ctx = new \App\Core\TenantContext($orgRef, $frnRef, 'USR-FRNADMIN000000001', 'FRANCHISE_ADMIN', 'FRANCHISE', null, 'test-req-id');
            $invite = $onboarding->issueInvite($ctx, null, 'USR-FRNADMIN000000001');

            $onboarding->register($invite['token'], [
                'firm_name'    => 'First Horizon Meditech',
                'constitution_type' => 'Pvt Ltd',
                'contact_name' => 'Ramesh Test',
                'designation'  => 'Director',
                'email'        => 'first.' . bin2hex(random_bytes(3)) . '@meditech.local',
                'password'     => 'SecurePass@123',
                'mobile'       => '9833445567',
                'gstin'        => '27AAACA1234A1Z6',
                'drug_license_no' => 'MH-MZ3-123457',
                'drug_license_validity' => '2030-12-31',
                'pan'          => 'AAACA1234B',
                'billing_address' => '101, Test Appt',
                'shipping_address' => '101, Test Appt',
                'pincode'      => '400001',
                'preferred_product_categories' => ['Pharma'],
                'documents' => [],
            ]);

            \Tests\Support\Assert::throws(
                fn() => $onboarding->register($invite['token'], [
                    'firm_name'    => 'Second Attempt',
                    'constitution_type' => 'Pvt Ltd',
                    'contact_name' => 'Ramesh Test',
                    'designation'  => 'Director',
                    'email'        => 'second@meditech.local',
                    'password'     => 'SecurePass@123',
                    'mobile'       => '9833445568',
                    'gstin'        => '27AAACA1234A1Z7',
                    'drug_license_no' => 'MH-MZ3-123458',
                    'drug_license_validity' => '2030-12-31',
                    'pan'          => 'AAACA1234C',
                    'billing_address' => '101, Test Appt',
                    'shipping_address' => '101, Test Appt',
                    'pincode'      => '400001',
                    'preferred_product_categories' => ['Pharma'],
                    'documents' => [],
                ]),
                \App\Core\Exceptions\ConflictException::class,
                'Reused invite token must throw ConflictException'
            );
        },
    ]
];
