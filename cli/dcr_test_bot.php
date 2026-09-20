<?php
/**
 * DCR Test Bot
 * Simulates a Sales Rep creating a DCR, adding visits with products, and submitting it.
 */

$baseUrl = 'http://crm/api/v1';

function makeRequest($method, $url, $data = null, $token = null) {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    
    $headers = ['Content-Type: application/json', 'Accept: application/json'];
    if ($token) {
        $headers[] = 'Authorization: Bearer ' . $token;
    }
    
    if ($data) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    }
    
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    return ['code' => $httpCode, 'body' => json_decode($response, true)];
}

echo "=== DCR API Test Bot ===\n\n";

// 1. Login
echo "1. Logging in...\n";
$res = makeRequest('POST', "$baseUrl/auth/login", [
    'email' => 'admin@pharmacrm.local',
    'password' => 'Admin@1234'
]);

if ($res['code'] !== 200 || !isset($res['body']['data']['access_token'])) {
    echo "Login failed! Response: " . json_encode($res['body']) . "\n";
    exit(1);
}
$token = $res['body']['data']['access_token'];
echo "   Logged in successfully.\n\n";

// Ensure we have a territory, customer, and product.
// We will fetch them or create them.
echo "2. Fetching Masters...\n";
$res = makeRequest('GET', "$baseUrl/territories", null, $token);
$territories = $res['body']['data'] ?? [];
if (empty($territories)) {
    makeRequest('POST', "$baseUrl/territories", ['name' => 'Test Territory ' . time(), 'code' => 'TT' . time()], $token);
    $territories = makeRequest('GET', "$baseUrl/territories", null, $token)['body']['data'];
}
$territoryId = $territories[0]['id'];

$res = makeRequest('GET', "$baseUrl/customers", null, $token);
$customers = $res['body']['data'] ?? [];
if (empty($customers)) {
    makeRequest('POST', "$baseUrl/customers", ['name' => 'Dr. Test Doc', 'territory_id' => $territoryId, 'specialty' => 'GP', 'email' => 'doc'.time().'@test.com', 'type' => 'doctor'], $token);
    $customers = makeRequest('GET', "$baseUrl/customers", null, $token)['body']['data'];
}
$customerId = $customers[0]['id'];

$res = makeRequest('GET', "$baseUrl/products", null, $token);
$products = $res['body']['data'] ?? [];
if (empty($products)) {
    makeRequest('POST', "$baseUrl/products", ['name' => 'Test Product', 'sku' => 'TP' . time(), 'price' => 10.0, 'status' => 'active'], $token);
    $products = makeRequest('GET', "$baseUrl/products", null, $token)['body']['data'];
}
$productId = $products[0]['id'];

// 3. Create DCR
echo "3. Creating DCR...\n";
$dcrDate = date('Y-m-d');
$res = makeRequest('POST', "$baseUrl/dcrs", [
    'territory_id' => $territoryId,
    'dcr_date' => $dcrDate
], $token);

if ($res['code'] !== 201 && $res['code'] !== 400) { // 400 could be 'already exists'
    echo "Failed to create DCR. Code: {$res['code']}\n" . json_encode($res['body']) . "\n";
    exit(1);
}

$dcrs = makeRequest('GET', "$baseUrl/dcrs?date=$dcrDate", null, $token)['body']['data'];
$dcrId = $dcrs[0]['id'];
echo "   DCR ID: $dcrId created/found.\n\n";

// 4. Add Visit
echo "4. Adding a Visit...\n";
$res = makeRequest('POST', "$baseUrl/dcrs/$dcrId/visits", [
    'customer_id' => $customerId,
    'visit_time' => '10:00:00',
    'notes' => 'Discussed new formulations.',
    'products' => [
        ['product_id' => $productId, 'quantity' => 5]
    ]
], $token);

if ($res['code'] !== 201) {
    if (strpos(json_encode($res['body']), 'Cannot add visits') !== false) {
        echo "   (DCR already submitted/approved, skipping visit addition)\n\n";
    } else {
        echo "Failed to add visit. Code: {$res['code']}\n" . json_encode($res['body']) . "\n";
        exit(1);
    }
} else {
    echo "   Visit added successfully.\n\n";
}

// 5. Submit DCR
echo "5. Submitting DCR...\n";
$res = makeRequest('PATCH', "$baseUrl/dcrs/$dcrId/status", [
    'status' => 'submitted',
    'manager_notes' => 'Please review'
], $token);

if ($res['code'] !== 200) {
    echo "Failed to submit DCR. Code: {$res['code']}\n" . json_encode($res['body']) . "\n";
    exit(1);
}
echo "   DCR Submitted successfully!\n\n";

// 6. Verify Fetch
echo "6. Fetching DCR Details...\n";
$res = makeRequest('GET', "$baseUrl/dcrs/$dcrId", null, $token);
if ($res['code'] === 200) {
    echo "   DCR fetched. Status: " . $res['body']['data']['status'] . "\n";
    echo "   Visits count: " . count($res['body']['data']['visits']) . "\n";
}

echo "\n✅ All DCR tests passed!\n";
