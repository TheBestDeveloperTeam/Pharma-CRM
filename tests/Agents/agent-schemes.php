<?php
return [
    'name'  => 'agent-schemes',
    'scope' => 'schemes',
    'group' => 'free_goods',
    'steps' => [
        // 1. Setup sample 10+1 scheme and rules
        function() {
            $frnRef = 'FRN-MUMBAI000000000001';
            $schemeRepo = \App\Core\Container::getInstance()->make(\App\Repositories\Contracts\SchemeRepositoryInterface::class);

            $schemeRef = 'SCH-TEST-10PLUS1-001';
            $existing = $schemeRepo->findByRef($frnRef, $schemeRef);
            if (!$existing) {
                $schemeRepo->createScheme([
                    'scheme_ref'       => $schemeRef,
                    'org_ref'          => 'ORG-PLATFORM0000000001',
                    'franchise_ref'    => $frnRef,
                    'scheme_name'      => 'Launch Promo 10+1 Free',
                    'start_date'       => '2026-01-01',
                    'end_date'         => '2026-12-31',
                    'priority'         => 10,
                    'stacking_allowed' => 0,
                    'tier_ref'         => null, // All tiers
                    'status'           => 'ACTIVE',
                    'created_by_ref'   => 'USR-FRNADMIN000000001',
                    'created_at'       => date('Y-m-d H:i:s'),
                ]);

                $schemeRepo->createRule([
                    'rule_ref'      => 'RUL-TEST-RULE-000001',
                    'org_ref'       => 'ORG-PLATFORM0000000001',
                    'franchise_ref' => $frnRef,
                    'scheme_ref'    => $schemeRef,
                    'product_ref'   => 'PRD-TEST000000000001',
                    'min_qty'       => 10,
                    'max_qty'       => null,
                    'free_qty'      => 1,
                ]);
            }
        },
        // 2. Test 10+1 table: Qty 9 -> 0 free
        function() {
            $calc = \App\Core\Container::getInstance()->make(\App\Domain\Schemes\SchemeCalculator::class);
            $res = $calc->calculate('FRN-MUMBAI000000000001', null, 'PRD-TEST000000000001', 9, '2026-03-01');
            \Tests\Support\Assert::eq(0, $res['free_qty'], 'Qty 9 yields 0 free');
            \Tests\Support\Assert::eq(9, $res['total_fulfil'], 'Total fulfill 9');
        },
        // 3. Test 10+1 table: Qty 10 -> 1 free
        function() {
            $calc = \App\Core\Container::getInstance()->make(\App\Domain\Schemes\SchemeCalculator::class);
            $res = $calc->calculate('FRN-MUMBAI000000000001', null, 'PRD-TEST000000000001', 10, '2026-03-01');
            \Tests\Support\Assert::eq(1, $res['free_qty'], 'Qty 10 yields 1 free');
            \Tests\Support\Assert::eq(11, $res['total_fulfil'], 'Total fulfill 11');
            \Tests\Support\Assert::true(in_array('SCH-TEST-10PLUS1-001', $res['scheme_refs'], true), 'Applied scheme ref');
        },
        // 4. Test 10+1 table: Qty 20 -> 2 free
        function() {
            $calc = \App\Core\Container::getInstance()->make(\App\Domain\Schemes\SchemeCalculator::class);
            $res = $calc->calculate('FRN-MUMBAI000000000001', null, 'PRD-TEST000000000001', 20, '2026-03-01');
            \Tests\Support\Assert::eq(2, $res['free_qty'], 'Qty 20 yields 2 free');
            \Tests\Support\Assert::eq(22, $res['total_fulfil'], 'Total fulfill 22');
        },
        // 5. Test 10+1 table: Qty 21 -> 2 free
        function() {
            $calc = \App\Core\Container::getInstance()->make(\App\Domain\Schemes\SchemeCalculator::class);
            $res = $calc->calculate('FRN-MUMBAI000000000001', null, 'PRD-TEST000000000001', 21, '2026-03-01');
            \Tests\Support\Assert::eq(2, $res['free_qty'], 'Qty 21 yields 2 free');
            \Tests\Support\Assert::eq(23, $res['total_fulfil'], 'Total fulfill 23');
        },
        // 6. Test 10+1 table: Qty 26 -> 2 free
        function() {
            $calc = \App\Core\Container::getInstance()->make(\App\Domain\Schemes\SchemeCalculator::class);
            $res = $calc->calculate('FRN-MUMBAI000000000001', null, 'PRD-TEST000000000001', 26, '2026-03-01');
            \Tests\Support\Assert::eq(2, $res['free_qty'], 'Qty 26 yields 2 free');
            \Tests\Support\Assert::eq(28, $res['total_fulfil'], 'Total fulfill 28');
        },
    ],
    'cleanup' => function() {},
];
