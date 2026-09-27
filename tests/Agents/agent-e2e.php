<?php
return [
    'name'  => 'agent-e2e',
    'scope' => 'orders',
    'group' => 'order-to-cash',
    'steps' => [
        // Full End-to-End Cycle: Receipt -> Order -> FEFO Reserve -> Invoice -> Dispatch -> Deliver -> Payment -> Zero Outstanding
        function() {
            $c = \App\Core\Container::getInstance();
            $invService = $c->make(\App\Domain\Inventory\InventoryService::class);
            $orderService = $c->make(\App\Domain\Orders\OrderService::class);
            $billingService = $c->make(\App\Domain\Billing\BillingService::class);
            $dispatchService = $c->make(\App\Domain\Dispatch\DispatchService::class);
            $paymentService = $c->make(\App\Domain\Payments\PaymentService::class);
            $partyService = $c->make(\App\Domain\Parties\PartyService::class);
            $invoiceRepo = $c->make(\App\Repositories\Contracts\InvoiceRepositoryInterface::class);

            $frnRef = 'FRN-MUMBAI000000000001';
            $orgRef = 'ORG-PLATFORM0000000001';
            $prodRef = 'PRD-TEST000000000001';

            $pc = '400' . rand(100, 999);
            $partyRef = $partyService->create([
                'org_ref'        => $orgRef,
                'franchise_ref'  => $frnRef,
                'party_code'     => 'PTY-E2E-' . bin2hex(random_bytes(4)),
                'firm_name'      => 'Apex E2E Pharma',
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

            // 1. Inward goods
            $invService->receiveGoods(
                $orgRef, $frnRef, $prodRef, 'BN-E2E-' . time(),
                date('Y-m-d', strtotime('+365 days')), 100, null, null, 'USR-FRNADMIN000000001'
            );

            // 2. Place Order
            $clientRef = 'CLI-E2E-' . time();
            $ord = $orderService->createOrder(
                $orgRef, $frnRef, $partyRef, $clientRef, 'PORTAL',
                [['product_ref' => $prodRef, 'paid_qty' => 10]],
                null, null, null, null, 'USR-FRNADMIN000000001'
            );
            \Tests\Support\Assert::eq('DRAFT', $ord['status'], 'Step 2: Order created as draft');
            $ord = $orderService->submitOrder($orgRef, $frnRef, $ord['order_ref'], 'USR-FRNADMIN000000001');
            \Tests\Support\Assert::eq('SUBMITTED', $ord['status'], 'Step 2.5: Order submitted');

            // 3. Confirm Order (FEFO Reservation)
            $confirmed = $orderService->confirmOrder($orgRef, $frnRef, $ord['order_ref'], 'USR-FRNADMIN000000001');
            \Tests\Support\Assert::eq('CONFIRMED', $confirmed['status'], 'Step 3: Order confirmed');

            // 4. Generate Invoice (Billing)
            $inv = $billingService->generateInvoice($orgRef, $frnRef, $ord['order_ref'], 'USR-FRNADMIN000000001');
            \Tests\Support\Assert::true(!empty($inv['invoice_no']), 'Step 4: Invoice generated');

            // 5. Dispatch Goods
            $lr = 'LR-E2E-' . rand(100000, 999999);
            $dsp = $dispatchService->create(
                $orgRef, $frnRef, $inv['invoice_ref'], null, $lr, null, 1, 'E2E test shipment', 'USR-FRNADMIN000000001'
            );
            \Tests\Support\Assert::eq('DISPATCHED', $dsp['status'], 'Step 5: Consignment dispatched');

            // 6. Deliver Goods
            $dispatchService->deliver($orgRef, $frnRef, $dsp['dispatch_ref'], 'USR-FRNADMIN000000001', 'Delivered');

            // 7. Receive Payment
            $invRow = $invoiceRepo->findByRef($frnRef, $inv['invoice_ref']);
            $invGrandTotal = (float)$invRow['grand_total'];

            $pay = $paymentService->recordPayment(
                $orgRef, $frnRef, $partyRef, date('Y-m-d'), $invGrandTotal, 'UPI', 'UPI-' . rand(1000, 9999), 'Full settle', true, 'USR-FRNADMIN000000001'
            );
            \Tests\Support\Assert::true(!empty($pay['payment_ref']), 'Step 7: Payment collected and allocated');

            // 8. Verify invoice fully paid
            $finalInv = $invoiceRepo->findByRef($frnRef, $inv['invoice_ref']);
            \Tests\Support\Assert::eq((float)$finalInv['grand_total'], (float)$finalInv['paid_total'], 'Invoice paid_total equals grand_total');
        }
    ]
];
