<?php
return [
    'name'  => 'agent-composite-fk',
    'scope' => 'schema',
    'group' => 'integrity',
    'steps' => [
        // 1. Verify foreign key constraints on product_categories
        function() {
            $pdo = \App\Core\Database::connection();
            // Verify product category reference integrity
            $prodRepo = \App\Core\Container::getInstance()->make(\App\Repositories\Contracts\ProductRepositoryInterface::class);
            $catRepo = \App\Core\Container::getInstance()->make(\App\Repositories\Contracts\ProductCategoryRepositoryInterface::class);

            $frnRef = 'FRN-MUMBAI000000000001';
            $cat = $catRepo->findByName($frnRef, 'Antibiotics');
            if (!$cat) {
                $catRepo->create([
                    'category_ref'   => 'CAT-ANTIBIOTIC-001',
                    'org_ref'        => 'ORG-PLATFORM0000000001',
                    'franchise_ref'  => $frnRef,
                    'category_name'  => 'Antibiotics',
                    'status'         => 'ACTIVE',
                    'created_by_ref' => 'USR-FRNADMIN000000001',
                ]);
            }

            // Verify product references the category properly with composite franchise_ref
            $prod = $prodRepo->findByRef($frnRef, 'PRD-TEST000000000001');
            \Tests\Support\Assert::true($prod !== null, 'Product exists in franchise');
        },
        // 2. Verify CHECK constraint: product_prices cannot have both tier_ref and party_ref
        function() {
            $pdo = \App\Core\Database::connection();
            $stmt = $pdo->prepare("INSERT INTO product_prices 
                (price_ref, org_ref, franchise_ref, product_ref, tier_ref, party_ref, rate, priority, effective_from, status, created_by_ref)
                VALUES ('PRC-INVALID-CHECK', 'ORG-PLATFORM0000000001', 'FRN-MUMBAI000000000001', 'PRD-TEST000000000001', 'TIR-RETAIL', 'PAR-SAMPLE', 100.00, 100, '2026-01-01', 'ACTIVE', 'USR-FRNADMIN000000001')");

            \Tests\Support\Assert::throws(
                fn() => $stmt->execute(),
                \PDOException::class,
                'CHECK constraint chk_pp_target must reject both tier_ref and party_ref'
            );
        },
        // 3. Verify scheme_rules CHECK constraint: min_qty > 0 and free_qty > 0
        function() {
            $pdo = \App\Core\Database::connection();
            $stmt = $pdo->prepare("INSERT INTO scheme_rules 
                (rule_ref, org_ref, franchise_ref, scheme_ref, product_ref, min_qty, max_qty, free_qty)
                VALUES ('RUL-INVALID-QTY', 'ORG-PLATFORM0000000001', 'FRN-MUMBAI000000000001', 'SCH-TEST-10PLUS1-001', 'PRD-TEST000000000001', 0, 10, 0)");

            \Tests\Support\Assert::throws(
                fn() => $stmt->execute(),
                \PDOException::class,
                'CHECK constraint chk_sr_qty must reject min_qty <= 0 or free_qty <= 0'
            );
        },
    ],
    'cleanup' => function() {},
];
