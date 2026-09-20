<?php
return [
    'name'  => 'agent-inventory',
    'scope' => 'inventory',
    'group' => 'order-to-cash',
    'steps' => [
        // 1. Goods receipt (GRN) creates batch and movement
        function() {
            $c = \App\Core\Container::getInstance();
            $invService = $c->make(\App\Domain\Inventory\InventoryService::class);
            $batchRepo = $c->make(\App\Repositories\Contracts\InventoryBatchRepositoryInterface::class);
            $movementRepo = $c->make(\App\Repositories\Contracts\InventoryMovementRepositoryInterface::class);

            $frnRef = 'FRN-MUMBAI000000000001';
            $orgRef = 'ORG-PLATFORM0000000001';
            $prodRef = 'PRD-TEST000000000001';
            $batchNo = 'BN-TEST-' . time();

            $batchRef = $invService->receiveGoods(
                $orgRef,
                $frnRef,
                $prodRef,
                $batchNo,
                date('Y-m-d', strtotime('+365 days')),
                100,
                date('Y-m-d'),
                'WH-RACK-A1',
                'USR-FRNADMIN000000001'
            );

            \Tests\Support\Assert::true(!empty($batchRef), 'Batch ref generated');

            $batch = $batchRepo->findByRef($frnRef, $batchRef);
            \Tests\Support\Assert::eq(100, (int)$batch['on_hand_qty'], 'On hand quantity is 100');
            \Tests\Support\Assert::eq(0, (int)$batch['reserved_qty'], 'Reserved quantity is 0');
            \Tests\Support\Assert::eq('SALEABLE', $batch['status'], 'Status is SALEABLE');

            $moves = $movementRepo->listByBatch($frnRef, $batchRef);
            \Tests\Support\Assert::eq(1, count($moves), 'Receipt movement created');
            \Tests\Support\Assert::eq('RECEIPT', $moves[0]['movement_type'], 'Movement type is RECEIPT');
            \Tests\Support\Assert::eq(100, (int)$moves[0]['qty'], 'Movement qty is 100');
        },
        // 2. Stock adjustment
        function() {
            $c = \App\Core\Container::getInstance();
            $invService = $c->make(\App\Domain\Inventory\InventoryService::class);
            $batchRepo = $c->make(\App\Repositories\Contracts\InventoryBatchRepositoryInterface::class);

            $frnRef = 'FRN-MUMBAI000000000001';
            $orgRef = 'ORG-PLATFORM0000000001';
            $prodRef = 'PRD-TEST000000000001';
            $batchNo = 'BN-ADJ-' . time();

            $batchRef = $invService->receiveGoods(
                $orgRef,
                $frnRef,
                $prodRef,
                $batchNo,
                date('Y-m-d', strtotime('+365 days')),
                50,
                null,
                null,
                'USR-FRNADMIN000000001'
            );

            $invService->adjustStock($orgRef, $frnRef, $batchRef, -5, 'Damaged in transit', 'USR-FRNADMIN000000001');

            $batch = $batchRepo->findByRef($frnRef, $batchRef);
            \Tests\Support\Assert::eq(45, (int)$batch['on_hand_qty'], 'Adjusted on hand qty is 45');
        },
        // 3. Near expiry list
        function() {
            $c = \App\Core\Container::getInstance();
            $invService = $c->make(\App\Domain\Inventory\InventoryService::class);
            $frnRef = 'FRN-MUMBAI000000000001';
            $orgRef = 'ORG-PLATFORM0000000001';

            // Receive batch expiring in 30 days
            $invService->receiveGoods(
                $orgRef,
                $frnRef,
                'PRD-TEST000000000001',
                'BN-NEAR-' . time(),
                date('Y-m-d', strtotime('+30 days')),
                20,
                null,
                null,
                'USR-FRNADMIN000000001'
            );

            $nearList = $invService->listNearExpiry($frnRef, 60);
            \Tests\Support\Assert::true(count($nearList) >= 1, 'Near expiry returns expiring batch');
        }
    ]
];
