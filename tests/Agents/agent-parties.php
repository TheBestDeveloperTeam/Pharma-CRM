<?php
return [
    'name'  => 'agent-parties',
    'scope' => 'parties',
    'group' => 'crm',
    'steps' => [
        // 1. Party creation with auto-generated code and composite uniqueness
        function() {
            $frnRef = 'FRN-MUMBAI000000000001';
            $orgRef = 'ORG-PLATFORM0000000001';
            $partyService = \App\Core\Container::getInstance()->make(\App\Domain\Parties\PartyService::class);
            $partyRepo = \App\Core\Container::getInstance()->make(\App\Repositories\Contracts\PartyRepositoryInterface::class);

            $ptyRef = $partyService->create([
                'org_ref'        => $orgRef,
                'franchise_ref'  => $frnRef,
                'firm_name'      => 'Apex Pharmaceuticals Distributors',
                'contact_name'   => 'Suresh Patel',
                'mobile'         => '9821122334',
                'email'          => 'apex.dist@pharma.local',
                'gstin'          => '27AABCU9603R1ZM',
                'credit_limit'   => 500000.00,
                'opening_outstanding' => 25000.00,
                'created_by_ref' => 'USR-FRNADMIN000000001',
            ]);

            $party = $partyRepo->findByRef($frnRef, $ptyRef);
            \Tests\Support\Assert::true($party !== null, 'Party created successfully');
            \Tests\Support\Assert::true(str_starts_with($party['party_code'], 'PTY-'), 'Auto-generated sequence code PTY-');
        },
        // 2. Duplicate party_code in same franchise must fail
        function() {
            $frnRef = 'FRN-MUMBAI000000000001';
            $orgRef = 'ORG-PLATFORM0000000001';
            $partyService = \App\Core\Container::getInstance()->make(\App\Domain\Parties\PartyService::class);

            $code = 'CUSTOM-CODE-' . bin2hex(random_bytes(3));
            $partyService->create([
                'org_ref'        => $orgRef,
                'franchise_ref'  => $frnRef,
                'party_code'     => $code,
                'firm_name'      => 'Custom Firm Alpha',
                'created_by_ref' => 'USR-FRNADMIN000000001',
            ]);

            \Tests\Support\Assert::throws(
                fn() => $partyService->create([
                    'org_ref'        => $orgRef,
                    'franchise_ref'  => $frnRef,
                    'party_code'     => $code,
                    'firm_name'      => 'Custom Firm Beta',
                    'created_by_ref' => 'USR-FRNADMIN000000001',
                ]),
                \App\Core\Exceptions\ConflictException::class,
                'Duplicate party code in same franchise throws ConflictException'
            );
        },
        // 3. Ledger summary computation
        function() {
            $frnRef = 'FRN-MUMBAI000000000001';
            $orgRef = 'ORG-PLATFORM0000000001';
            $partyService = \App\Core\Container::getInstance()->make(\App\Domain\Parties\PartyService::class);
            $partyRepo = \App\Core\Container::getInstance()->make(\App\Repositories\Contracts\PartyRepositoryInterface::class);

            $ref = $partyService->create([
                'org_ref'             => $orgRef,
                'franchise_ref'       => $frnRef,
                'firm_name'           => 'Ledger Check Medical',
                'opening_outstanding' => 15000.00,
                'created_by_ref'      => 'USR-FRNADMIN000000001',
            ]);

            $ledger = $partyRepo->getLedgerSummary($frnRef, $ref);
            \Tests\Support\Assert::equals('15000.00', $ledger['opening_outstanding'], 'Opening balance matches');
            \Tests\Support\Assert::equals('15000.00', $ledger['current_outstanding'], 'Current balance matches initial opening');
        },
    ]
];
