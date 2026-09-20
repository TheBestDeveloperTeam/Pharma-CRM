<?php
return [
    'name'  => 'agent-territory',
    'scope' => 'territory',
    'group' => 'crm',
    'steps' => [
        // 1. Allocate exclusive pincode territory
        function() {
            $frnRef = 'FRN-MUMBAI000000000001';
            $orgRef = 'ORG-PLATFORM0000000001';
            $terrService = \App\Core\Container::getInstance()->make(\App\Domain\Territory\TerritoryService::class);
            $partyService = \App\Core\Container::getInstance()->make(\App\Domain\Parties\PartyService::class);

            $pincode = '400' . str_pad((string)random_int(100, 999), 3, '0', STR_PAD_LEFT);
            $GLOBALS['test_agent_territory_pin'] = $pincode;

            $partyA = $partyService->create([
                'org_ref'        => $orgRef,
                'franchise_ref'  => $frnRef,
                'firm_name'      => 'Party Terr A ' . $pincode,
                'created_by_ref' => 'USR-FRNADMIN000000001',
            ]);
            $GLOBALS['test_agent_territory_party_a'] = $partyA;

            $terrRef = $terrService->create([
                'org_ref'        => $orgRef,
                'franchise_ref'  => $frnRef,
                'party_ref'      => $partyA,
                'level'          => 'PINCODE',
                'pincode'        => $pincode,
                'effective_from' => '2026-01-01',
                'is_exclusive'   => 1,
                'created_by_ref' => 'USR-FRNADMIN000000001',
            ]);

            \Tests\Support\Assert::true(!empty($terrRef), 'Exclusive territory created');
        },
        // 2. Conflicting exclusive territory for same pincode throws ConflictException
        function() {
            $frnRef = 'FRN-MUMBAI000000000001';
            $orgRef = 'ORG-PLATFORM0000000001';
            $terrService = \App\Core\Container::getInstance()->make(\App\Domain\Territory\TerritoryService::class);
            $partyService = \App\Core\Container::getInstance()->make(\App\Domain\Parties\PartyService::class);

            $pincode = $GLOBALS['test_agent_territory_pin'];

            $partyB = $partyService->create([
                'org_ref'        => $orgRef,
                'franchise_ref'  => $frnRef,
                'firm_name'      => 'Party Terr B ' . $pincode,
                'created_by_ref' => 'USR-FRNADMIN000000001',
            ]);
            $GLOBALS['test_agent_territory_party_b'] = $partyB;

            \Tests\Support\Assert::throws(
                fn() => $terrService->create([
                    'org_ref'        => $orgRef,
                    'franchise_ref'  => $frnRef,
                    'party_ref'      => $partyB,
                    'level'          => 'PINCODE',
                    'pincode'        => $pincode, // Overlaps exclusive party A
                    'effective_from' => '2026-01-01',
                    'is_exclusive'   => 1,
                    'created_by_ref' => 'USR-FRNADMIN000000001',
                ]),
                \App\Core\Exceptions\ConflictException::class,
                'Overlapping exclusive territory throws ConflictException'
            );
        },
        // 3. TerritoryValidator blocks unauthorized party and allows authorized party
        function() {
            $frnRef = 'FRN-MUMBAI000000000001';
            $validator = \App\Core\Container::getInstance()->make(\App\Domain\Territory\TerritoryValidator::class);

            $pincode = $GLOBALS['test_agent_territory_pin'];
            $partyA = $GLOBALS['test_agent_territory_party_a'];
            $partyB = $GLOBALS['test_agent_territory_party_b'];

            $resA = $validator->validate($frnRef, $partyA, $pincode, '2026-06-01');
            \Tests\Support\Assert::equals(\App\Domain\Territory\TerritoryValidator::ALLOWED, $resA['status'], 'Party A is ALLOWED');

            $resB = $validator->validate($frnRef, $partyB, $pincode, '2026-06-01');
            \Tests\Support\Assert::equals(\App\Domain\Territory\TerritoryValidator::BLOCKED, $resB['status'], 'Party B is BLOCKED due to exclusive reservation');
        },
    ]
];
