<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

define('BASE_PATH', dirname(__DIR__));

$app = require_once BASE_PATH . '/bootstrap/app.php';
$jwtService = $app->getContainer()->make(\App\Core\JwtService::class);

echo "Generating JWT Key Pairs...\n";

try {
    $jwtService->generateKeyPair();
    echo "Keys generated successfully in storage/keys/ \n";
} catch (\Exception $e) {
    echo "Error generating keys: " . $e->getMessage() . "\n";
}
