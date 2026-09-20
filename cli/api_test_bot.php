<?php

declare(strict_types=1);

/**
 * API Test Bot - Tests all Phase 1 API endpoints
 */

$baseUrl = 'http://crm/api/v1'; // Localhost virtual host setup

define('BASE_PATH', dirname(__DIR__));
require_once BASE_PATH . '/vendor/autoload.php';
require_once BASE_PATH . '/bootstrap/app.php';

echo "Running API Test Bot...\n";
echo "Base URL: $baseUrl\n\n";

function makeRequest(string $method, string $url, array $data = [], array $headers = []) {
    $ch = curl_init($url);
    
    $defaultHeaders = ['Content-Type: application/json', 'Accept: application/json'];
    $headers = array_merge($defaultHeaders, $headers);
    
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    
    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        if (!empty($data)) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }
    } elseif ($method === 'PATCH' || $method === 'PUT' || $method === 'DELETE') {
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        if (!empty($data)) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }
    }

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    return [
        'code' => $httpCode,
        'body' => json_decode($response, true) ?: $response
    ];
}

function assertSuccess(string $name, array $res, int $expectedCode = 200) {
    echo "Testing [$name]... ";
    if ($res['code'] === $expectedCode) {
        echo "✅ Passed ({$res['code']})\n";
        return true;
    } else {
        echo "❌ Failed (Expected $expectedCode, Got {$res['code']})\n";
        print_r($res['body']);
        return false;
    }
}

// 1. Health Check
$res = makeRequest('GET', 'http://crm/api/v1/health');
assertSuccess('Health Check', $res, 200);

// 2. Auth - Login
$adminEmail = getenv('ADMIN_EMAIL') ?: 'admin@crm.local';
$adminPass  = getenv('ADMIN_PASS') ?: 'Admin@1234';

$res = makeRequest('POST', "$baseUrl/auth/login", [
    'email' => $adminEmail,
    'password' => $adminPass
]);
if (!assertSuccess('Login as Admin', $res, 200)) {
    exit("Cannot continue without login.\n");
}

$accessToken = $res['body']['data']['access_token'];
$refreshToken = $res['body']['data']['refresh_token'];
$authHeader = ["Authorization: Bearer $accessToken"];

// 3. Auth - Me
$res = makeRequest('GET', "$baseUrl/auth/me", [], $authHeader);
assertSuccess('Get Current User (Me)', $res, 200);

// 4. Auth - Refresh
$res = makeRequest('POST', "$baseUrl/auth/refresh", ['refresh_token' => $refreshToken]);
assertSuccess('Refresh Token', $res, 200);
$accessToken = $res['body']['data']['access_token'];
$authHeader = ["Authorization: Bearer $accessToken"];

// 5. Roles - List
$res = makeRequest('GET', "$baseUrl/roles", [], $authHeader);
assertSuccess('List Roles', $res, 200);

// 6. Permissions - List
$res = makeRequest('GET', "$baseUrl/permissions", [], $authHeader);
assertSuccess('List Permissions', $res, 200);

// 7. Users - Create User
$testEmail = 'testuser' . time() . '@crm.local';
$res = makeRequest('POST', "$baseUrl/users", [
    'name' => 'Test User',
    'email' => $testEmail,
    'password' => 'TestPass@1234',
    'role' => 'sales_rep'
], $authHeader);
assertSuccess('Create User', $res, 201);
$testUserId = $res['body']['data']['id'] ?? null;

if ($testUserId) {
    // 8. Users - Get User
    $res = makeRequest('GET', "$baseUrl/users/$testUserId", [], $authHeader);
    assertSuccess('Get User', $res, 200);

    // 9. Users - Update User
    $res = makeRequest('PATCH', "$baseUrl/users/$testUserId", [
        'name' => 'Updated Test User'
    ], $authHeader);
    assertSuccess('Update User', $res, 200);

    // 10. Users - Deactivate
    $res = makeRequest('POST', "$baseUrl/users/$testUserId/deactivate", [], $authHeader);
    assertSuccess('Deactivate User', $res, 200);

    // 11. Users - Activate
    $res = makeRequest('POST', "$baseUrl/users/$testUserId/activate", [], $authHeader);
    assertSuccess('Activate User', $res, 200);
} else {
    echo "⚠️ Skipping User tests because creation failed.\n";
}

// 12. Auth - Logout
$res = makeRequest('POST', "$baseUrl/auth/logout", [], $authHeader);
assertSuccess('Logout', $res, 200);

echo "\nAPI Tests Completed.\n";
