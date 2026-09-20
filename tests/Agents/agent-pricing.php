<?php
return [
    'name'  => 'agent-pricing',
    'scope' => 'pricing',
    'group' => 'calculation',
    'steps' => [
        // 1. Setup sample product and pricing tiers
        function() {
            $frnRef = 'FRN-MUMBAI000000000001';
            $prodRepo = \App\Core\Container::getInstance()->make(\App\Repositories\Contracts\ProductRepositoryInterface::class);
            $tierRepo = \App\Core\Container::getInstance()->make(\App\Repositories\Contracts\PricingTierRepositoryInterface::class);
            $priceRepo = \App\Core\Container::getInstance()->make(\App\Repositories\Contracts\ProductPriceRepositoryInterface::class);

            // Create test product with franchise_rate 150.00
            $sku = 'TEST-AMOX-500';
            $prod = $prodRepo->findBySku($frnRef, $sku);
            if (!$prod) {
                $prodRef = 'PRD-TEST000000000001';
                $prodRepo->create([
                    'product_ref'    => $prodRef,
                    'org_ref'        => 'ORG-PLATFORM0000000001',
                    'franchise_ref'  => $frnRef,
                    'sku'            => $sku,
                    'product_name'   => 'Amoxicillin 500mg',
                    'mrp'            => 200.00,
                    'pts'            => 160.00,
                    'franchise_rate' => 150.00,
                    'gst_percent'    => 12.00,
                    'status'         => 'ACTIVE',
                    'created_by_ref' => 'USR-FRNADMIN000000001',
                    'created_at'     => date('Y-m-d H:i:s'),
                ]);
            }

            // Create TIER rule (STOCKIST -> 140.00)
            if (!$priceRepo->findByRef($frnRef, 'PRC-TIER-STOCKIST-01')) {
                $priceRepo->create([
                    'price_ref'      => 'PRC-TIER-STOCKIST-01',
                    'org_ref'        => 'ORG-PLATFORM0000000001',
                    'franchise_ref'  => $frnRef,
                    'product_ref'    => 'PRD-TEST000000000001',
                    'tier_ref'       => 'TIR-STOCKIST',
                    'party_ref'      => null,
                    'rate'           => 140.00,
                    'priority'       => 100,
                    'effective_from' => '2026-01-01',
                    'effective_to'   => null,
                    'status'         => 'ACTIVE',
                    'created_by_ref' => 'USR-FRNADMIN000000001',
                    'created_at'     => date('Y-m-d H:i:s'),
                ]);
            }

            // Create PARTY rule (Special Party -> 135.00)
            if (!$priceRepo->findByRef($frnRef, 'PRC-PARTY-SPECIAL-01')) {
                $priceRepo->create([
                    'price_ref'      => 'PRC-PARTY-SPECIAL-01',
                    'org_ref'        => 'ORG-PLATFORM0000000001',
                    'franchise_ref'  => $frnRef,
                    'product_ref'    => 'PRD-TEST000000000001',
                    'tier_ref'       => null,
                    'party_ref'      => 'PAR-SPECIAL0000000001',
                    'rate'           => 135.00,
                    'priority'       => 50,
                    'effective_from' => '2026-01-01',
                    'effective_to'   => null,
                    'status'         => 'ACTIVE',
                    'created_by_ref' => 'USR-FRNADMIN000000001',
                    'created_at'     => date('Y-m-d H:i:s'),
                ]);
            }
        },
        // 2. Resolve party-specific price (highest priority: PARTY)
        function() {
            $resolver = \App\Core\Container::getInstance()->make(\App\Domain\Pricing\PriceResolver::class);
            $res = $resolver->resolve('FRN-MUMBAI000000000001', 'PRD-TEST000000000001', 'PAR-SPECIAL0000000001', 'TIR-STOCKIST', '2026-03-01');

            \Tests\Support\Assert::eq(135.0, $res['rate'], 'Party specific price resolved');
            \Tests\Support\Assert::eq('PARTY', $res['rate_source'], 'Source is PARTY');
            \Tests\Support\Assert::eq('PRC-PARTY-SPECIAL-01', $res['price_ref'], 'Matching price_ref');
        },
        // 3. Resolve tier price when no party price exists (TIER)
        function() {
            $resolver = \App\Core\Container::getInstance()->make(\App\Domain\Pricing\PriceResolver::class);
            $res = $resolver->resolve('FRN-MUMBAI000000000001', 'PRD-TEST000000000001', 'PAR-OTHER00000000002', 'TIR-STOCKIST', '2026-03-01');

            \Tests\Support\Assert::eq(140.0, $res['rate'], 'Tier based price resolved');
            \Tests\Support\Assert::eq('TIER', $res['rate_source'], 'Source is TIER');
            \Tests\Support\Assert::eq('PRC-TIER-STOCKIST-01', $res['price_ref'], 'Matching tier price_ref');
        },
        // 4. Resolve default franchise rate when no party or tier price (DEFAULT)
        function() {
            $resolver = \App\Core\Container::getInstance()->make(\App\Domain\Pricing\PriceResolver::class);
            $res = $resolver->resolve('FRN-MUMBAI000000000001', 'PRD-TEST000000000001', null, null, '2026-03-01');

            \Tests\Support\Assert::eq(150.0, $res['rate'], 'Default franchise rate resolved');
            \Tests\Support\Assert::eq('DEFAULT', $res['rate_source'], 'Source is DEFAULT');
            \Tests\Support\Assert::true($res['price_ref'] === null, 'No price_ref for default rate');
        },
        // 5. Money helper integer-paise arithmetic verification
        function() {
            \Tests\Support\Assert::eq('1500.00', \App\Support\Money::multiply('150.00', 10), 'Multiply 150 x 10');
            \Tests\Support\Assert::eq('135.00', \App\Support\Money::discount('150.00', 10.0), '10% discount on 150');
            $gst = \App\Support\Money::applyGst('100.00', 18.0);
            \Tests\Support\Assert::eq('18.00', $gst['gst_amount'], '18% GST calculation');
            \Tests\Support\Assert::eq('118.00', $gst['grand_total'], 'Total with GST');
        },
    ],
    'cleanup' => function() {},
];
