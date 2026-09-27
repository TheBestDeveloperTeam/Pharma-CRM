<?php
return [
    'name'  => 'agent-billing',
    'scope' => 'billing',
    'group' => 'order-to-cash',
    'steps' => [
        // 1. Generate invoice from confirmed order (gapless sequence, frozen snapshots, stock consumption)
        function() {
            $c = \App\Core\Container::getInstance();
            $orderService = $c->make(\App\Domain\Orders\OrderService::class);
            $invService = $c->make(\App\Domain\Inventory\InventoryService::class);
            $billingService = $c->make(\App\Domain\Billing\BillingService::class);
            $invoiceRepo = $c->make(\App\Repositories\Contracts\InvoiceRepositoryInterface::class);
            $batchRepo = $c->make(\App\Repositories\Contracts\InventoryBatchRepositoryInterface::class);
            $partyService = $c->make(\App\Domain\Parties\PartyService::class);
            $prodRepo = $c->make(\App\Repositories\Contracts\ProductRepositoryInterface::class);

            $frnRef = 'FRN-MUMBAI000000000001';
            $orgRef = 'ORG-PLATFORM0000000001';
            $prodRef = 'PRD-BILL-' . bin2hex(random_bytes(4));

            // Create isolated product for this test
            $prodRepo->create([
                'product_ref'    => $prodRef,
                'org_ref'        => $orgRef,
                'franchise_ref'  => $frnRef,
                'sku'            => 'SKU-BILL-' . time(),
                'product_name'   => 'Billing Test Tablet',
                'franchise_rate' => 50.00,
                'status'         => 'ACTIVE',
                'created_by_ref' => 'USR-FRNADMIN000000001',
            ]);

            $pc = '400' . rand(100, 999);
            $partyRef = $partyService->create([
                'org_ref'        => $orgRef,
                'franchise_ref'  => $frnRef,
                'party_code'     => 'PTY-BILL-' . bin2hex(random_bytes(4)),
                'firm_name'      => 'Apex Billing Pharma',
                'pincode'        => $pc,
                'credit_limit'   => 500000.00,
                'status'         => 'ACTIVE',
                'created_by_ref' => 'USR-FRNADMIN000000001',
            ]);

            $c->make(\App\Domain\Territory\TerritoryService::class)->create([
                'org_ref' => $orgRef,
                'franchise_ref' => $frnRef,
                'party_ref' => $partyRef,
                'level' => 'PINCODE',
                'pincode' => $pc,
                'effective_from' => '2026-01-01',
                'is_exclusive' => 1,
                'created_by_ref' => 'USR-FRNADMIN000000001',
            ]);

            $db = $c->make(\App\Core\Database::class);
            $db->prepare("UPDATE franchises SET state_ref = 'STA-MAH' WHERE franchise_ref = ?")->execute([$frnRef]);
            $db->prepare("INSERT IGNORE INTO pincodes (pincode, state_ref, district_ref) VALUES (?, 'STA-MAH', 'DST-PNE')")->execute([$pc]);

            // Add stock
            $batchRef = $invService->receiveGoods(
                $orgRef, $frnRef, $prodRef, 'BN-BILL-' . time(),
                date('Y-m-d', strtotime('+300 days')), 50, null, null, 'USR-FRNADMIN000000001'
            );

            // Create and confirm order for 10 units
            $ord = $orderService->createOrder(
                $orgRef, $frnRef, $partyRef, 'CLI-BILL-' . time(), 'ADMIN',
                [['product_ref' => $prodRef, 'paid_qty' => 10]],
                null, null, null, null, 'USR-FRNADMIN000000001'
            );
            $orderService->submitOrder($orgRef, $frnRef, $ord['order_ref'], 'USR-FRNADMIN000000001');
            $orderService->confirmOrder($orgRef, $frnRef, $ord['order_ref'], 'USR-FRNADMIN000000001');

            // Generate invoice
            $inv = $billingService->generateInvoice($orgRef, $frnRef, $ord['order_ref'], 'USR-FRNADMIN000000001');

            \Tests\Support\Assert::true(!empty($inv['invoice_ref']), 'Invoice ref generated');
            \Tests\Support\Assert::true(!empty($inv['invoice_no']), 'Invoice no sequential number generated');

            $invoiceRecord = $invoiceRepo->findByRef($frnRef, $inv['invoice_ref']);
            \Tests\Support\Assert::eq('POSTED', $invoiceRecord['status'], 'Invoice status is POSTED');

            $items = $invoiceRepo->getItems($frnRef, $inv['invoice_ref']);
            \Tests\Support\Assert::true(count($items) >= 1, 'Invoice items created');
            \Tests\Support\Assert::true(!empty($items[0]['batch_no_snapshot']), 'Batch snapshot frozen');
            \Tests\Support\Assert::true(!empty($items[0]['expiry_snapshot']), 'Expiry snapshot frozen');

            // Verify physical stock was NOT decremented yet (it happens at dispatch)
            $batch = $batchRepo->findByRef($frnRef, $batchRef);
            \Tests\Support\Assert::eq(50, (int)$batch['on_hand_qty'], 'On hand physical stock remains 50 upon billing');
            \Tests\Support\Assert::eq(10, (int)$batch['reserved_qty'], 'Reserved stock remains 10 upon billing');
        }
    ]
];
