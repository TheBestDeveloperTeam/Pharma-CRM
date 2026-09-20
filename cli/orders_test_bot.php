<?php
/**
 * Orders & Inventory Test Bot
 * Simulates a Sales Rep checking inventory, placing an order, and an Admin approving it.
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

echo "=== Orders API Test Bot ===\n\n";

// 1. Login
echo "1. Logging in as Admin...\n";
$res = makeRequest('POST', "$baseUrl/auth/login", [
    'email' => 'admin@pharmacrm.local',
    'password' => 'Admin@1234'
]);

if ($res['code'] !== 200) { echo "Login failed!\n"; exit(1); }
$token = $res['body']['data']['access_token'];

// Need a customer and a product
$customers = makeRequest('GET', "$baseUrl/customers", null, $token)['body']['data'] ?? [];
$products = makeRequest('GET', "$baseUrl/products", null, $token)['body']['data'] ?? [];

if (empty($customers) || empty($products)) {
    echo "Error: Run masters_test_bot.php first to seed customers and products.\n";
    exit(1);
}

$customerId = $customers[0]['id'];
$productId = $products[0]['id'];

// 2. Check Inventory
echo "2. Checking Inventory...\n";
$invRes = makeRequest('GET', "$baseUrl/inventory", null, $token);
$inventory = $invRes['body']['data'] ?? [];
$startingQty = 0;
foreach ($inventory as $i) {
    if ($i['product_id'] == $productId) {
        $startingQty = $i['quantity_available'];
        break;
    }
}
if ($startingQty < 5) {
    echo "   Injecting inventory for product ID $productId...\n";
    $pdo = new PDO("mysql:host=localhost;dbname=crm;charset=utf8mb4", "root", "");
    $pdo->exec("INSERT INTO inventory (product_id, quantity_available) VALUES ($productId, 100) ON DUPLICATE KEY UPDATE quantity_available = quantity_available + 100");
    $startingQty += 100;
}
echo "   Product ID $productId Starting Qty: $startingQty\n\n";

// 3. Place Order
echo "3. Placing Order...\n";
$res = makeRequest('POST', "$baseUrl/orders", [
    'customer_id' => $customerId,
    'notes' => 'Urgent clinic restock',
    'items' => [
        ['product_id' => $productId, 'quantity' => 5]
    ]
], $token);

if ($res['code'] !== 201) {
    echo "Order failed: " . json_encode($res['body']) . "\n";
    exit(1);
}
$orderId = $res['body']['data']['id'];
echo "   Order $orderId placed successfully. Total: " . $res['body']['data']['total_amount'] . "\n\n";

// 4. Approve Order
echo "4. Approving Order (as Admin)...\n";
$res = makeRequest('PATCH', "$baseUrl/orders/$orderId/status", [
    'status' => 'approved',
    'notes' => 'Looks good.'
], $token);

if ($res['code'] !== 200) {
    echo "Approval failed: " . json_encode($res['body']) . "\n";
    exit(1);
}
echo "   Order Approved.\n\n";

// 5. Verify Inventory Deduction
echo "5. Verifying Inventory Deduction...\n";
$invRes = makeRequest('GET', "$baseUrl/inventory", null, $token);
$inventory = $invRes['body']['data'] ?? [];
$endingQty = 0;
foreach ($inventory as $i) {
    if ($i['product_id'] == $productId) {
        $endingQty = $i['quantity_available'];
        break;
    }
}
echo "   Product ID $productId Ending Qty: $endingQty\n";

if ($endingQty === $startingQty - 5) {
    echo "\n✅ Inventory accurately deducted!\n";
    echo "✅ All Order tests passed!\n";
} else {
    echo "\n❌ Inventory math failed! Expected " . ($startingQty - 5) . " got $endingQty\n";
    exit(1);
}
