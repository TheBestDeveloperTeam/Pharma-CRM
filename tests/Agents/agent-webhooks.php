<?php
return [
    'name'  => 'agent-webhooks',
    'scope' => 'webhooks',
    'group' => 'crm',
    'steps' => [
        // 1. Create Webhook source with AES-256-GCM encrypted secret
        function() {
            $frnRef = 'FRN-MUMBAI000000000001';
            $orgRef = 'ORG-PLATFORM0000000001';
            $whService = \App\Core\Container::getInstance()->make(\App\Domain\Webhooks\WebhookService::class);

            $source = $whService->createSource($orgRef, $frnRef, 'IndiaMART Ingestion', 'USR-FRNADMIN000000001');
            \Tests\Support\Assert::true(!empty($source['endpoint_slug']), 'Generated unique slug');
            \Tests\Support\Assert::true(!empty($source['secret']), 'Returned plaintext secret once');

            // Resolve and verify secret decrypted correctly
            $resolved = $whService->resolveSource($source['endpoint_slug']);
            \Tests\Support\Assert::equals($source['secret'], $resolved['decrypted_secret'], 'AES-256-GCM decrypted secret matches');
        },
        // 2. Ingest valid lead webhook with HMAC verification
        function() {
            $frnRef = 'FRN-MUMBAI000000000001';
            $orgRef = 'ORG-PLATFORM0000000001';
            $whService = \App\Core\Container::getInstance()->make(\App\Domain\Webhooks\WebhookService::class);
            $ingestCtrl = \App\Core\Container::getInstance()->make(\App\Http\Controllers\Api\V1\WebhookIngestionController::class);

            $source = $whService->createSource($orgRef, $frnRef, 'TradeIndia', 'USR-FRNADMIN000000001');

            $payload = [
                'external_event_id' => 'EVT-TRADE-' . bin2hex(random_bytes(4)),
                'contact_name'      => 'Dr. Vikram Seth',
                'firm_name'         => 'Seth Polyclinic',
                'mobile'            => '9811122233',
            ];
            $body = json_encode($payload);
            $sig = 'sha256=' . hash_hmac('sha256', $body, $source['secret']);
            $time = time();

            $req = \App\Core\Request::capture();
            $refBody = new \ReflectionProperty($req, 'rawBody');
            $refBody->setAccessible(true);
            $refBody->setValue($req, $body);

            $refHeaders = new \ReflectionProperty($req, 'headers');
            $refHeaders->setAccessible(true);
            $refHeaders->setValue($req, [
                'x-signature' => $sig,
                'x-timestamp' => (string)$time,
            ]);

            $res = $ingestCtrl->ingest($req, $source['endpoint_slug']);
            \Tests\Support\Assert::equals(202, $res->status(), 'Webhook accepted with 202');
        },
        // 3. Invalid signature throws UnauthorizedException
        function() {
            $whService = \App\Core\Container::getInstance()->make(\App\Domain\Webhooks\WebhookService::class);
            $ingestCtrl = \App\Core\Container::getInstance()->make(\App\Http\Controllers\Api\V1\WebhookIngestionController::class);

            $source = $whService->createSource('ORG-PLATFORM0000000001', 'FRN-MUMBAI000000000001', 'BadSig Source', 'USR-FRNADMIN000000001');

            $body = json_encode(['external_event_id' => 'BAD-123']);
            $req = \App\Core\Request::capture();
            $refBody = new \ReflectionProperty($req, 'rawBody');
            $refBody->setAccessible(true);
            $refBody->setValue($req, $body);

            $refHeaders = new \ReflectionProperty($req, 'headers');
            $refHeaders->setAccessible(true);
            $refHeaders->setValue($req, [
                'x-signature' => 'sha256=invalidhashvalue1234567890',
                'x-timestamp' => (string)time(),
            ]);

            \Tests\Support\Assert::throws(
                fn() => $ingestCtrl->ingest($req, $source['endpoint_slug']),
                \App\Core\Exceptions\UnauthorizedException::class,
                'Invalid HMAC signature throws UnauthorizedException'
            );
        },
    ]
];
