<?php
declare(strict_types=1);

/**
 * Agent OpenAPI Parity Checker
 * Asserts that all registered API routes are documented in openapi.yaml
 */
return [
    'name'  => 'agent-openapi',
    'scope' => 'documentation',
    'group' => 'contract',
    'steps' => [
        // 1. Assert openapi.yaml exists and is non-empty
        function() {
            $specFile = dirname(__DIR__, 2) . '/public/api-docs/openapi.yaml';
            \Tests\Support\Assert::true(file_exists($specFile), 'openapi.yaml must exist');
            $content = file_get_contents($specFile);
            \Tests\Support\Assert::true(strlen($content) > 2000, 'openapi.yaml must contain detailed documentation');
        },
        // 2. Assert key path components are documented
        function() {
            $specFile = dirname(__DIR__, 2) . '/public/api-docs/openapi.yaml';
            $content = file_get_contents($specFile);

            $requiredPaths = [
                '/oauth/token',
                '/health',
                '/ready',
                '/super/organizations',
                '/super/franchises',
                '/super/dashboard/stats',
                '/admin/users',
                '/admin/products',
                '/admin/orders',
                '/admin/invoices',
                '/admin/dispatches',
                '/admin/payments',
                '/admin/reports/{type}',
                '/notifications',
                '/portal/catalogue',
                '/portal/cart/calculate',
                '/portal/orders',
                '/portal/invoices',
                '/portal/outstanding'
            ];

            foreach ($requiredPaths as $path) {
                \Tests\Support\Assert::true(
                    str_contains($content, $path . ':'),
                    "OpenAPI spec must document endpoint: {$path}"
                );
            }
        },
        // 3. Assert security schemes and headers defined
        function() {
            $specFile = dirname(__DIR__, 2) . '/public/api-docs/openapi.yaml';
            $content = file_get_contents($specFile);

            \Tests\Support\Assert::true(str_contains($content, 'bearerAuth:'), 'bearerAuth security scheme must be documented');
            \Tests\Support\Assert::true(str_contains($content, 'Idempotency-Key'), 'Idempotency-Key header must be documented');
            \Tests\Support\Assert::true(str_contains($content, 'ErrorEnvelope:'), 'ErrorEnvelope schema must be defined');
        }
    ],
    'cleanup' => function() {},
];
