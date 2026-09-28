<?php
return [
    'name'  => 'agent-payments',
    'scope' => 'payments',
    'group' => 'order-to-cash',
    'steps' => [
        // 1. Record payment & auto-allocate against open invoice
        function() {
            $c = \App\Core\Container::getInstance();
            $orderService = $c->make(\App\Domain\Orders\OrderService::class);
            $invService = $c->make(\App\Domain\Inventory\InventoryService::class);
            $billingService = $c->make(\App\Domain\Billing\BillingService::class);
            $paymentService = $c->make(\App\Domain\Payments\PaymentService::class);
            $invoiceRepo = $c->make(\App\Repositories\Contracts\InvoiceRepositoryInterface::class);
            $paymentRepo = $c->make(\App\Repositories\Contracts\PaymentRepositoryInterface::class);
            $partyService = $c->make(\App\Domain\Parties\PartyService::class);

            $frnRef = 'FRN-MUMBAI000000000001';
            $orgRef = 'ORG-PLATFORM0000000001';
            $prodRef = 'PRD-TEST000000000001';

            $db = \App\Core\Database::connection();
            do {
                $pc = '400' . rand(100, 999);
                $allocated = (int)$db->query("SELECT COUNT(*) FROM party_territories WHERE pincode = '{$pc}' AND status = 'ACTIVE'")->fetchColumn();
            } while ($allocated > 0);
            $partyRef = $partyService->create([
                'org_ref'        => $orgRef,
                'franchise_ref'  => $frnRef,
                'party_code'     => 'PTY-PAY-' . bin2hex(random_bytes(4)),
                'firm_name'      => 'Apex Payments Pharma',
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
            $invService->receiveGoods(
                $orgRef, $frnRef, $prodRef, 'BN-PAY-' . time(),
                date('Y-m-d', strtotime('+300 days')), 20, null, null, 'USR-FRNADMIN000000001'
            );

            // Order & Invoice
            $ord = $orderService->createOrder(
                $orgRef, $frnRef, $partyRef, 'CLI-PAY-' . time(), 'ADMIN',
                [['product_ref' => $prodRef, 'paid_qty' => 2]],
                null, null, null, null, 'USR-FRNADMIN000000001'
            );
            $orderService->submitOrder($orgRef, $frnRef, $ord['order_ref'], 'USR-FRNADMIN000000001');
            $orderService->confirmOrder($orgRef, $frnRef, $ord['order_ref'], 'USR-FRNADMIN000000001');
            $inv = $billingService->generateInvoice($orgRef, $frnRef, $ord['order_ref'], 'USR-FRNADMIN000000001');

            $invoiceRow = $invoiceRepo->findByRef($frnRef, $inv['invoice_ref']);
            $invTotal = (float)$invoiceRow['grand_total'];

            // Record payment for the invoice amount
            $pay = $paymentService->recordPayment(
                $orgRef,
                $frnRef,
                $partyRef,
                date('Y-m-d'),
                $invTotal,
                'NEFT',
                'UTR' . rand(100000, 999999),
                'Full payment against invoice',
                true,
                'USR-FRNADMIN000000001'
            );

            \Tests\Support\Assert::true(!empty($pay['payment_ref']), 'Payment ref generated');
            \Tests\Support\Assert::true(count($pay['allocations']) >= 1, 'Payment was allocated to open invoice');

            // Verify invoice paid_total matches
            $updatedInv = $invoiceRepo->findByRef($frnRef, $inv['invoice_ref']);
            \Tests\Support\Assert::true((float)$updatedInv['paid_total'] > 0, 'Invoice paid total is updated');
        }
    ]
];
