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

            $invite = $onboarding->issueInvite($orgRef, $frnRef, null, 'USR-FRNADMIN000000001');
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

            $invite = $onboarding->issueInvite($orgRef, $frnRef, null, 'USR-FRNADMIN000000001');

            $res = $onboarding->completeRegistration($invite['token'], [
                'firm_name'    => 'New Horizon Meditech',
                'contact_name' => 'Anil Deshmukh',
                'email'        => 'horizon.' . bin2hex(random_bytes(3)) . '@meditech.local',
                'password'     => 'SecurePass@123',
                'mobile'       => '9833445566',
            ]);

            \Tests\Support\Assert::equals('SUCCESS', $res['status'], 'Registration succeeded');
            \Tests\Support\Assert::true(!empty($res['party_ref']), 'Party created');
            \Tests\Support\Assert::true(!empty($res['user_ref']), 'Portal user created');
        },
        // 3. Re-using same invite token must fail (single-use)
        function() {
            $frnRef = 'FRN-MUMBAI000000000001';
            $orgRef = 'ORG-PLATFORM0000000001';
            $onboarding = \App\Core\Container::getInstance()->make(\App\Domain\Onboarding\OnboardingService::class);

            $invite = $onboarding->issueInvite($orgRef, $frnRef, null, 'USR-FRNADMIN000000001');

            $onboarding->completeRegistration($invite['token'], [
                'firm_name'    => 'First Horizon Meditech',
                'email'        => 'first.' . bin2hex(random_bytes(3)) . '@meditech.local',
                'password'     => 'SecurePass@123',
            ]);

            \Tests\Support\Assert::throws(
                fn() => $onboarding->completeRegistration($invite['token'], [
                    'firm_name'    => 'Second Attempt',
                    'email'        => 'second@meditech.local',
                    'password'     => 'SecurePass@123',
                ]),
                \App\Core\Exceptions\ValidationException::class,
                'Reused invite token must throw ValidationException'
            );
        },
    ]
];
