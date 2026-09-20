<?php
return [
    'name'  => 'agent-fefo',
    'scope' => 'inventory',
    'group' => 'order-to-cash',
    'steps' => [
        // 1. FEFO allocation picks earliest expiry first
        function() {
            $c = \App\Core\Container::getInstance();
            $invService = $c->make(\App\Domain\Inventory\InventoryService::class);
            $fefo = $c->make(\App\Domain\Inventory\FefoAllocator::class);
            $batchRepo = $c->make(\App\Repositories\Contracts\InventoryBatchRepositoryInterface::class);
            $prodRepo = $c->make(\App\Repositories\Contracts\ProductRepositoryInterface::class);

            $frnRef = 'FRN-MUMBAI000000000001';
            $orgRef = 'ORG-PLATFORM0000000001';
            $prodRef = 'PRD-FEFO-' . bin2hex(random_bytes(4));

            // Create fresh product for FEFO testing so other batches don't interfere
            $prodRepo->create([
                'product_ref'    => $prodRef,
                'org_ref'        => $orgRef,
                'franchise_ref'  => $frnRef,
                'sku'            => 'SKU-FEFO-' . time(),
                'product_name'   => 'FEFO Test Syrup',
                'franchise_rate' => 100.00,
                'status'         => 'ACTIVE',
                'created_by_ref' => 'USR-FRNADMIN000000001',
            ]);

            // Batch A: expires in 60 days, qty 10
            $batchA = $invService->receiveGoods(
                $orgRef, $frnRef, $prodRef, 'BN-FEFO-A-' . time(),
                date('Y-m-d', strtotime('+60 days')), 10, null, null, 'USR-FRNADMIN000000001'
            );

            // Batch B: expires in 120 days, qty 20
            $batchB = $invService->receiveGoods(
                $orgRef, $frnRef, $prodRef, 'BN-FEFO-B-' . time(),
                date('Y-m-d', strtotime('+120 days')), 20, null, null, 'USR-FRNADMIN000000001'
            );

            // Allocate 15 units -> should take 10 from Batch A and 5 from Batch B
            $res = $fefo->allocate($orgRef, $frnRef, 'ORD-FEFO-TEST-1', [
                ['order_item_ref' => 'OIT-FEFO-1', 'product_ref' => $prodRef, 'qty' => 15]
            ], 'USR-FRNADMIN000000001');

            \Tests\Support\Assert::eq(2, count($res), 'Two batch allocations made');
            \Tests\Support\Assert::eq($batchA, $res[0]['batch_ref'], 'First allocation is from earlier expiry batch A');
            \Tests\Support\Assert::eq(10, $res[0]['qty'], 'Batch A took 10 units');
            \Tests\Support\Assert::eq($batchB, $res[1]['batch_ref'], 'Second allocation is from batch B');
            \Tests\Support\Assert::eq(5, $res[1]['qty'], 'Batch B took remaining 5 units');

            $rowA = $batchRepo->findByRef($frnRef, $batchA);
            $rowB = $batchRepo->findByRef($frnRef, $batchB);
            \Tests\Support\Assert::eq(10, (int)$rowA['reserved_qty'], 'Batch A has 10 reserved');
            \Tests\Support\Assert::eq(5, (int)$rowB['reserved_qty'], 'Batch B has 5 reserved');
        },
        // 2. Release order stock returns reserved stock to pool
        function() {
            $c = \App\Core\Container::getInstance();
            $fefo = $c->make(\App\Domain\Inventory\FefoAllocator::class);
            $resRepo = $c->make(\App\Repositories\Contracts\StockReservationRepositoryInterface::class);

            $frnRef = 'FRN-MUMBAI000000000001';
            $orgRef = 'ORG-PLATFORM0000000001';
            $orderRef = 'ORD-FEFO-TEST-1';

            $fefo->releaseOrderStock($orgRef, $frnRef, $orderRef, 'USR-FRNADMIN000000001');

            $activeRes = $resRepo->getActiveForOrder($frnRef, $orderRef);
            \Tests\Support\Assert::eq(0, count($activeRes), 'Active reservations are now 0');
        }
    ]
];
