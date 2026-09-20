<?php

declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__));
require_once BASE_PATH . '/vendor/autoload.php';
require_once BASE_PATH . '/bootstrap/app.php';

$baseUrl = 'http://crm/api/v1';

echo "Running Masters API Test Bot...\n";
echo "Base URL: $baseUrl\n\n";

$adminEmail = env('ADMIN_EMAIL', 'admin@pharmacrm.local');
$adminPass  = env('ADMIN_PASS', 'Admin@1234');

function makeRequest($method, $url, $data = null, $token = null) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    
    $headers = ['Content-Type: application/json'];
    if ($token) {
        $headers[] = "Authorization: Bearer $token";
    }
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        if ($data) curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    } elseif ($method === 'PATCH') {
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PATCH');
        if ($data) curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    } elseif ($method === 'DELETE') {
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
    }

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    return ['code' => $httpCode, 'body' => json_decode($response, true)];
}

// 1. Login to get token
echo "Logging in...\n";
$res = makeRequest('POST', "$baseUrl/auth/login", ['email' => $adminEmail, 'password' => $adminPass]);
if ($res['code'] !== 200) {
    die("❌ Login failed: " . print_r($res['body'], true));
}
$token = $res['body']['data']['access_token'];
echo "✅ Logged in successfully.\n\n";

// --- Territories ---
echo "Testing [Create Territory]... ";
$res = makeRequest('POST', "$baseUrl/territories", ['name' => 'West Zone ' . time(), 'type' => 'zone'], $token);
if ($res['code'] === 201) {
    $territoryId = $res['body']['data']['id'];
    echo "✅ Passed (201)\n";
} else {
    echo "❌ Failed (Expected 201, Got {$res['code']})\n";
    print_r($res['body']);
}

echo "Testing [List Territories]... ";
$res = makeRequest('GET', "$baseUrl/territories", null, $token);
if ($res['code'] === 200 && !empty($res['body']['data'])) {
    echo "✅ Passed (200)\n";
} else {
    echo "❌ Failed\n";
    print_r($res['body']);
}

// --- Products ---
echo "Testing [Create Product]... ";
$res = makeRequest('POST', "$baseUrl/products", ['name' => 'Aspirin 100mg ' . time(), 'sku_code' => 'ASP-' . time(), 'price' => 12.50], $token);
if ($res['code'] === 201) {
    echo "✅ Passed (201)\n";
} else {
    echo "❌ Failed\n";
    print_r($res['body']);
    exit(1);
}

echo "Testing [List Products]... ";
$res = makeRequest('GET', "$baseUrl/products", null, $token);
if ($res['code'] === 200 && !empty($res['body']['data'])) {
    echo "✅ Passed (200)\n";
} else {
    echo "❌ Failed\n";
    print_r($res['body']);
}

// --- Customers ---
echo "Testing [Create Customer]... ";
$res = makeRequest('POST', "$baseUrl/customers", [
    'name' => 'Dr. Anil Sharma',
    'type' => 'doctor',
    'specialty' => 'Neurology',
    'territory_id' => $territoryId ?? 1
], $token);

if ($res['code'] === 201) {
    echo "✅ Passed (201)\n";
} else {
    echo "❌ Failed\n";
    print_r($res['body']);
    exit(1);
}

echo "Testing [List Customers]... ";
$res = makeRequest('GET', "$baseUrl/customers", null, $token);
if ($res['code'] === 200 && !empty($res['body']['data'])) {
    echo "✅ Passed (200)\n";
} else {
    echo "❌ Failed\n";
    print_r($res['body']);
}

echo "\nMasters API Tests Completed.\n";
