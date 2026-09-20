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

            $partyRef = $partyService->create([
                'org_ref'        => $orgRef,
                'franchise_ref'  => $frnRef,
                'party_code'     => 'PTY-BILL-' . bin2hex(random_bytes(4)),
                'firm_name'      => 'Apex Billing Pharma',
                'pincode'        => '411001',
                'credit_limit'   => 500000.00,
                'status'         => 'ACTIVE',
                'created_by_ref' => 'USR-FRNADMIN000000001',
            ]);

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

            // Verify physical stock was decremented from batch
            $batch = $batchRepo->findByRef($frnRef, $batchRef);
            \Tests\Support\Assert::eq(40, (int)$batch['on_hand_qty'], 'On hand physical stock decremented from 50 to 40 upon billing');
            \Tests\Support\Assert::eq(0, (int)$batch['reserved_qty'], 'Reserved stock is released/consumed to 0');
        }
    ]
];
