<?php
return [
    'name'  => 'agent-orders',
    'scope' => 'orders',
    'group' => 'order-to-cash',
    'steps' => [
        // 1. Create order with party pricing, schemes, and paise calculation
        function() {
            $c = \App\Core\Container::getInstance();
            $orderService = $c->make(\App\Domain\Orders\OrderService::class);
            $orderRepo = $c->make(\App\Repositories\Contracts\OrderRepositoryInterface::class);
            $partyService = $c->make(\App\Domain\Parties\PartyService::class);

            $frnRef = 'FRN-MUMBAI000000000001';
            $orgRef = 'ORG-PLATFORM0000000001';
            $prodRef = 'PRD-TEST000000000001';

            // Ensure test party exists with an unassigned pincode
            $partyRef = $partyService->create([
                'org_ref'        => $orgRef,
                'franchise_ref'  => $frnRef,
                'party_code'     => 'PTY-ORD-' . bin2hex(random_bytes(4)),
                'firm_name'      => 'Apex Mumbai Pharma',
                'pincode'        => '411001', // Pune / unreserved
                'credit_limit'   => 500000.00,
                'status'         => 'ACTIVE',
                'created_by_ref' => 'USR-FRNADMIN000000001',
            ]);
            $GLOBALS['test_orders_party_ref'] = $partyRef;

            $clientRef = 'CLI-ORD-' . time();

            $res = $orderService->createOrder(
                $orgRef,
                $frnRef,
                $partyRef,
                $clientRef,
                'PORTAL',
                [
                    ['product_ref' => $prodRef, 'paid_qty' => 10]
                ],
                null,
                '101 MG Road, Pune',
                '411001',
                'Test order',
                'USR-FRNADMIN000000001'
            );

            \Tests\Support\Assert::true(!empty($res['order_ref']), 'Order ref created');
            \Tests\Support\Assert::true(!empty($res['order_no']), 'Order no generated');
            \Tests\Support\Assert::eq('SUBMITTED', $res['status'], 'Status is SUBMITTED');

            $order = $orderRepo->findByRef($frnRef, $res['order_ref']);
            \Tests\Support\Assert::eq($clientRef, $order['client_order_ref'], 'Client order ref preserved');

            $items = $orderRepo->getItems($frnRef, $res['order_ref']);
            \Tests\Support\Assert::eq(1, count($items), '1 line item present');
            \Tests\Support\Assert::eq(10, (int)$items[0]['paid_qty'], 'Paid qty is 10');
            \Tests\Support\Assert::true((int)$items[0]['free_qty'] >= 0, 'Free qty evaluated');
        },
        // 2. Duplicate client_order_ref per franchise is blocked
        function() {
            $c = \App\Core\Container::getInstance();
            $orderService = $c->make(\App\Domain\Orders\OrderService::class);

            $frnRef = 'FRN-MUMBAI000000000001';
            $orgRef = 'ORG-PLATFORM0000000001';
            $partyRef = $GLOBALS['test_orders_party_ref'];
            $prodRef = 'PRD-TEST000000000001';
            $clientRef = 'CLI-DUP-TEST-' . time();

            // Create initial order
            $orderService->createOrder(
                $orgRef, $frnRef, $partyRef, $clientRef, 'SALES',
                [['product_ref' => $prodRef, 'paid_qty' => 5]],
                null, null, null, null, 'USR-FRNADMIN000000001'
            );

            // Attempt duplicate
            \Tests\Support\Assert::throws(
                fn() => $orderService->createOrder(
                    $orgRef, $frnRef, $partyRef, $clientRef, 'SALES',
                    [['product_ref' => $prodRef, 'paid_qty' => 5]],
                    null, null, null, null, 'USR-FRNADMIN000000001'
                ),
                \App\Core\Exceptions\ConflictException::class,
                'Duplicate client_order_ref in same franchise throws ConflictException'
            );
        },
        // 3. Confirm order reserves stock via FEFO
        function() {
            $c = \App\Core\Container::getInstance();
            $orderService = $c->make(\App\Domain\Orders\OrderService::class);
            $invService = $c->make(\App\Domain\Inventory\InventoryService::class);
            $resRepo = $c->make(\App\Repositories\Contracts\StockReservationRepositoryInterface::class);

            $frnRef = 'FRN-MUMBAI000000000001';
            $orgRef = 'ORG-PLATFORM0000000001';
            $partyRef = $GLOBALS['test_orders_party_ref'];
            $prodRef = 'PRD-TEST000000000001';

            // Add stock to ensure availability
            $invService->receiveGoods(
                $orgRef, $frnRef, $prodRef, 'BN-ORDCONF-' . time(),
                date('Y-m-d', strtotime('+180 days')), 50, null, null, 'USR-FRNADMIN000000001'
            );

            $ord = $orderService->createOrder(
                $orgRef, $frnRef, $partyRef, 'CLI-CONF-' . time(), 'ADMIN',
                [['product_ref' => $prodRef, 'paid_qty' => 5]],
                null, null, null, null, 'USR-FRNADMIN000000001'
            );

            $confirmRes = $orderService->confirmOrder($orgRef, $frnRef, $ord['order_ref'], 'USR-FRNADMIN000000001');
            \Tests\Support\Assert::eq('CONFIRMED', $confirmRes['status'], 'Order status is CONFIRMED');

            $activeRes = $resRepo->getActiveForOrder($frnRef, $ord['order_ref']);
            \Tests\Support\Assert::true(count($activeRes) >= 1, 'Stock reservations created on confirm');
        }
    ]
];
