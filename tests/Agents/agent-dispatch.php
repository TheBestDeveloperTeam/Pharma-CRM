<?php
return [
    'name'  => 'agent-dispatch',
    'scope' => 'dispatch',
    'group' => 'order-to-cash',
    'steps' => [
        // 1. Create dispatch for posted invoice & advance to DELIVERED
        function() {
            $c = \App\Core\Container::getInstance();
            $orderService = $c->make(\App\Domain\Orders\OrderService::class);
            $invService = $c->make(\App\Domain\Inventory\InventoryService::class);
            $billingService = $c->make(\App\Domain\Billing\BillingService::class);
            $dispatchService = $c->make(\App\Domain\Dispatch\DispatchService::class);
            $dispatchRepo = $c->make(\App\Repositories\Contracts\DispatchRepositoryInterface::class);
            $orderRepo = $c->make(\App\Repositories\Contracts\OrderRepositoryInterface::class);
            $partyService = $c->make(\App\Domain\Parties\PartyService::class);

            $frnRef = 'FRN-MUMBAI000000000001';
            $orgRef = 'ORG-PLATFORM0000000001';
            $prodRef = 'PRD-TEST000000000001';

            $partyRef = $partyService->create([
                'org_ref'        => $orgRef,
                'franchise_ref'  => $frnRef,
                'party_code'     => 'PTY-DSP-' . bin2hex(random_bytes(4)),
                'firm_name'      => 'Apex Dispatch Pharma',
                'pincode'        => '411001',
                'credit_limit'   => 500000.00,
                'status'         => 'ACTIVE',
                'created_by_ref' => 'USR-FRNADMIN000000001',
            ]);

            // Add stock
            $invService->receiveGoods(
                $orgRef, $frnRef, $prodRef, 'BN-DSP-' . time(),
                date('Y-m-d', strtotime('+300 days')), 30, null, null, 'USR-FRNADMIN000000001'
            );

            // Order & Invoice
            $ord = $orderService->createOrder(
                $orgRef, $frnRef, $partyRef, 'CLI-DSP-' . time(), 'ADMIN',
                [['product_ref' => $prodRef, 'paid_qty' => 5]],
                null, null, null, null, 'USR-FRNADMIN000000001'
            );
            $orderService->confirmOrder($orgRef, $frnRef, $ord['order_ref'], 'USR-FRNADMIN000000001');
            $inv = $billingService->generateInvoice($orgRef, $frnRef, $ord['order_ref'], 'USR-FRNADMIN000000001');

            // Create Dispatch
            $lr = 'VRL-MUM-' . rand(100000, 999999);
            $dsp = $dispatchService->createDispatch(
                $orgRef,
                $frnRef,
                $inv['invoice_ref'],
                null,
                $lr,
                'https://tracking.example.com/' . $lr,
                2,
                '2 cartons fragile medicine',
                'USR-FRNADMIN000000001'
            );

            \Tests\Support\Assert::true(!empty($dsp['dispatch_ref']), 'Dispatch ref generated');
            \Tests\Support\Assert::eq('DISPATCHED', $dsp['status'], 'Status is DISPATCHED');

            // Order transitioned to DISPATCHED
            $orderRow = $orderRepo->findByRef($frnRef, $ord['order_ref']);
            \Tests\Support\Assert::eq('DISPATCHED', $orderRow['status'], 'Order status is DISPATCHED');

            // Deliver
            $dispatchService->markDelivered($frnRef, $dsp['dispatch_ref'], 'USR-FRNADMIN000000001');
            $dspRow = $dispatchRepo->findByRef($frnRef, $dsp['dispatch_ref']);
            \Tests\Support\Assert::eq('DELIVERED', $dspRow['status'], 'Dispatch is DELIVERED');

            $orderRow2 = $orderRepo->findByRef($frnRef, $ord['order_ref']);
            \Tests\Support\Assert::eq('DELIVERED', $orderRow2['status'], 'Order is DELIVERED');
        }
    ]
];
