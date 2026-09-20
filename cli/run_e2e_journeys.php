<?php
// cli/run_e2e_journeys.php

require_once __DIR__ . '/../vendor/autoload.php';

$baseUrl = "http://crm/api/v1";

function logMsg($msg, $type = 'info') {
    $colors = [
        'info' => "\033[36m",
        'success' => "\033[32m",
        'error' => "\033[31m",
        'warning' => "\033[33m",
        'reset' => "\033[0m"
    ];
    echo $colors[$type] . $msg . $colors['reset'] . "\n";
}

function makeRequest($method, $endpoint, $data = null, $token = null) {
    global $baseUrl;
    
    $ch = curl_init($baseUrl . $endpoint);
    
    $headers = ['Content-Type: application/json', 'Accept: application/json'];
    if ($token) {
        $headers[] = "Authorization: Bearer $token";
    }
    
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
    
    if ($data !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    }
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    return [
        'code' => $httpCode,
        'body' => json_decode($response, true)
    ];
}

function login($email, $password) {
    $res = makeRequest('POST', '/auth/login', ['email' => $email, 'password' => $password]);
    if ($res['code'] === 200 && isset($res['body']['data']['access_token'])) {
        return $res['body']['data']['access_token'];
    }
    throw new Exception("Login failed for $email: " . json_encode($res['body']));
}

$adminToken = null;
$rep1Token = null;

try {
    logMsg("============================================");
    logMsg("  Running 9 Unique End-to-End Journeys");
    logMsg("============================================");

    // Initial log-ins
    $adminToken = login('admin@pharmacrm.local', 'Admin@1234');
    $rep1Token = login('john.rep@pharmacrm.local', 'password');

    // Let's fetch some valid products
    $products = makeRequest('GET', '/products', null, $rep1Token)['body']['data'];
    $prodId1 = $products[0]['id'];
    $prodId2 = $products[1]['id'];

    logMsg("\n>> Journey 1: The Perfect Day (Rep logs in, creates DCR, visits, submits)");
    $uniqueDate = date('Y-m-d', strtotime('+' . rand(10, 1000) . ' days'));
    // 1. Create DCR
    $res = makeRequest('POST', '/dcrs', ['dcr_date' => $uniqueDate, 'territory_id' => 5], $rep1Token);
    if ($res['code'] !== 201 && $res['code'] !== 200) throw new Exception("DCR creation failed: " . json_encode($res));
    $dcrId = $res['body']['data']['id'] ?? $res['body']['data']['dcr']['id'];
    
    // 2. Add Visit
    $res = makeRequest('POST', "/dcrs/$dcrId/visits", [
        'customer_id' => 1,
        'discussion_topics' => 'Discussed new products',
        'samples' => [['product_id' => $prodId1, 'quantity' => 2]]
    ], $rep1Token);
    if ($res['code'] !== 201) throw new Exception("Visit creation failed: " . json_encode($res));
    
    // 3. Submit DCR
    $res = makeRequest('PATCH', "/dcrs/$dcrId/status", ['status' => 'submitted'], $rep1Token);
    if ($res['code'] !== 200) throw new Exception("DCR submission failed: " . json_encode($res));
    logMsg("✅ Journey 1 Passed", 'success');


    logMsg("\n>> Journey 2: The Big Sale (Rep places order, Admin approves)");
    $res = makeRequest('POST', '/orders', [
        'customer_id' => 1,
        'items' => [['product_id' => $prodId1, 'quantity' => 50]],
        'notes' => 'Big sale!'
    ], $rep1Token);
    if ($res['code'] !== 201) throw new Exception("Order creation failed: " . json_encode($res));
    $orderId = $res['body']['data']['id'];
    
    $res = makeRequest('PATCH', "/orders/$orderId/status", ['status' => 'approved', 'notes' => 'Approved by admin'], $adminToken);
    if ($res['code'] !== 200) throw new Exception("Order approval failed: " . json_encode($res));
    logMsg("✅ Journey 2 Passed", 'success');


    logMsg("\n>> Journey 3: The Out-of-Stock Scenario");
    $res = makeRequest('POST', '/orders', [
        'customer_id' => 1,
        'items' => [['product_id' => $prodId1, 'quantity' => 999999]]
    ], $rep1Token);
    if ($res['code'] !== 400 && $res['code'] !== 409) throw new Exception("Order creation SHOULD have failed with 400/409 (insufficient inventory): " . json_encode($res));
    logMsg("✅ Journey 3 Passed", 'success');


    logMsg("\n>> Journey 4: The Rejected Order");
    $res = makeRequest('POST', '/orders', [
        'customer_id' => 2,
        'items' => [['product_id' => $prodId2, 'quantity' => 5]]
    ], $rep1Token);
    $orderId3 = $res['body']['data']['id'];
    
    $res = makeRequest('PATCH', "/orders/$orderId3/status", ['status' => 'rejected', 'notes' => 'Rejected'], $adminToken);
    if ($res['code'] !== 200) throw new Exception("Order rejection failed: " . json_encode($res));
    logMsg("✅ Journey 4 Passed", 'success');


    logMsg("\n>> Journey 5: The Late DCR (Edit submitted)");
    $res = makeRequest('POST', "/dcrs/$dcrId/visits", [ // Using $dcrId from Journey 1 which is submitted
        'customer_id' => 2,
        'discussion_topics' => 'Late visit',
        'samples' => []
    ], $rep1Token);
    if ($res['code'] !== 400 && $res['code'] !== 403) throw new Exception("Should not allow visit to submitted DCR: " . json_encode($res));
    logMsg("✅ Journey 5 Passed", 'success');


    logMsg("\n>> Journey 6: Admin Data Entry");
    $res = makeRequest('POST', '/products', [
        'name' => 'New Drug XYZ',
        'sku_code' => 'XYZ-100-' . rand(1000, 9999),
        'price' => 100
    ], $adminToken);
    if ($res['code'] !== 201) throw new Exception("Product creation failed: " . json_encode($res));
    logMsg("✅ Journey 6 Passed", 'success');


    logMsg("\n>> Journey 7: The New Hire");
    $newEmail = 'newguy' . rand(1000, 9999) . '@pharmacrm.local';
    $res = makeRequest('POST', '/users', [
        'name' => 'New Guy',
        'email' => $newEmail,
        'password' => 'Admin@1234',
        'role' => 'sales_rep'
    ], $adminToken);
    if ($res['code'] !== 201) throw new Exception("User creation failed: " . json_encode($res));
    
    $newGuyToken = login($newEmail, 'Admin@1234');
    if (!$newGuyToken) throw new Exception("New guy could not login");
    logMsg("✅ Journey 7 Passed", 'success');


    logMsg("\n>> Journey 8: The Validation Assault");
    $res = makeRequest('POST', '/products', [], $adminToken); // Empty payload
    if ($res['code'] !== 422) throw new Exception("Expected 422 for empty product: " . json_encode($res));
    
    $res = makeRequest('POST', '/dcrs', ['dcr_date' => 'invalid-date'], $rep1Token); 
    if ($res['code'] !== 422 && $res['code'] !== 400) throw new Exception("Expected 422/400 for invalid date: " . json_encode($res));
    logMsg("✅ Journey 8 Passed", 'success');


    logMsg("\n>> Journey 9: The Deactivated User");
    $newGuyId = makeRequest('GET', '/users', null, $adminToken)['body']['data'][0]['id']; // just get any ID, wait better get new guy
    
    // Better: Fetch /users and find newguy
    $users = makeRequest('GET', '/users', null, $adminToken)['body']['data'];
    $targetId = null;
    foreach($users as $u) if($u['email'] === $newEmail) $targetId = $u['id'];
    
    $res = makeRequest('POST', "/users/$targetId/deactivate", [], $adminToken);
    if ($res['code'] !== 200) throw new Exception("Failed to deactivate: " . json_encode($res));
    
    $res = makeRequest('GET', '/products', null, $newGuyToken);
    if ($res['code'] !== 401 && $res['code'] !== 403) throw new Exception("Deactivated user should get 401/403, got: " . json_encode($res));
    logMsg("✅ Journey 9 Passed", 'success');
    
    logMsg("\n============================================", 'success');
    logMsg("  🎉 ALL 9 E2E JOURNEYS COMPLETED AND PASSED!", 'success');
    logMsg("============================================", 'success');

} catch (Exception $e) {
    logMsg("\n❌ JOURNEY FAILED: " . $e->getMessage(), 'error');
    exit(1);
}
