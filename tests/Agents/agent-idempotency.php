<?php
return [
    'name'  => 'agent-idempotency',
    'scope' => 'orders',
    'group' => 'idempotency',
    'steps' => [
        // 1. Lock and complete idempotent write
        function() {
            $c = \App\Core\Container::getInstance();
            $repo = $c->make(\App\Repositories\Contracts\IdempotencyRepositoryInterface::class);

            $frnRef = 'FRN-MUMBAI000000000001';
            $orgRef = 'ORG-PLATFORM0000000001';
            $key = 'test-idemp-key-' . bin2hex(random_bytes(8));
            $hash = hash('sha256', 'POST|/api/v1/admin/orders|{"party_ref":"xyz"}');

            $locked = $repo->lock($orgRef, $frnRef, $key, 'POST', '/api/v1/admin/orders', $hash);
            \Tests\Support\Assert::true($locked, 'First lock succeeds');

            // Duplicate in-progress lock fails
            $locked2 = $repo->lock($orgRef, $frnRef, $key, 'POST', '/api/v1/admin/orders', $hash);
            \Tests\Support\Assert::false($locked2, 'Duplicate lock while IN_PROGRESS is blocked');

            // Complete request
            $completed = $repo->complete($frnRef, $key, 201, ['order_ref' => 'ORD-TEST-001']);
            \Tests\Support\Assert::true($completed, 'Complete sets status and cached response');

            $saved = $repo->find($frnRef, $key);
            \Tests\Support\Assert::eq('COMPLETED', $saved['status'], 'Status is COMPLETED');
            \Tests\Support\Assert::eq(201, (int)$saved['response_status'], 'Status code 201 stored');
        },
        // 2. Tenant isolation of idempotency keys
        function() {
            $c = \App\Core\Container::getInstance();
            $repo = $c->make(\App\Repositories\Contracts\IdempotencyRepositoryInterface::class);

            $frn1 = 'FRN-MUMBAI000000000001';
            $frn2 = 'FRN-DELHI000000000002';
            $orgRef = 'ORG-PLATFORM0000000001';
            $key = 'test-shared-key-' . bin2hex(random_bytes(8));
            $hash = hash('sha256', 'sample-payload');

            $locked1 = $repo->lock($orgRef, $frn1, $key, 'POST', '/test', $hash);
            \Tests\Support\Assert::true($locked1, 'Tenant 1 locks key');

            $locked2 = $repo->lock($orgRef, $frn2, $key, 'POST', '/test', $hash);
            \Tests\Support\Assert::true($locked2, 'Tenant 2 can use same key without collision (composite unique)');
        }
    ]
];
