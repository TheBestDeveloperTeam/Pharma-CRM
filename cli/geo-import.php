<?php
declare(strict_types=1);

// Usage: php cli/geo-import.php

require __DIR__ . '/../bootstrap/app.php';

$pdo = \App\Core\Database::connection();

echo "Seeding India Geographic Reference Data...\n";

$states = [
    ['state_ref' => 'STE-MH000000000001', 'state_code' => 'MH', 'state_name' => 'Maharashtra'],
    ['state_ref' => 'STE-DL000000000002', 'state_code' => 'DL', 'state_name' => 'Delhi'],
    ['state_ref' => 'STE-KA000000000003', 'state_code' => 'KA', 'state_name' => 'Karnataka'],
    ['state_ref' => 'STE-GJ000000000004', 'state_code' => 'GJ', 'state_name' => 'Gujarat'],
    ['state_ref' => 'STE-TN000000000005', 'state_code' => 'TN', 'state_name' => 'Tamil Nadu'],
    ['state_ref' => 'STE-UP000000000006', 'state_code' => 'UP', 'state_name' => 'Uttar Pradesh'],
    ['state_ref' => 'STE-WB000000000007', 'state_code' => 'WB', 'state_name' => 'West Bengal'],
    ['state_ref' => 'STE-RJ000000000008', 'state_code' => 'RJ', 'state_name' => 'Rajasthan'],
    ['state_ref' => 'STE-MP000000000009', 'state_code' => 'MP', 'state_name' => 'Madhya Pradesh'],
    ['state_ref' => 'STE-TG000000000010', 'state_code' => 'TG', 'state_name' => 'Telangana'],
];

$stmtState = $pdo->prepare("INSERT INTO states (state_ref, state_code, state_name) VALUES (:ref, :code, :name) ON DUPLICATE KEY UPDATE state_name = VALUES(state_name)");
foreach ($states as $s) {
    $stmtState->execute([':ref' => $s['state_ref'], ':code' => $s['state_code'], ':name' => $s['state_name']]);
}

$districts = [
    ['district_ref' => 'DST-MUMBAI00000001', 'state_ref' => 'STE-MH000000000001', 'district_name' => 'Mumbai City'],
    ['district_ref' => 'DST-PUNE0000000002', 'state_ref' => 'STE-MH000000000001', 'district_name' => 'Pune'],
    ['district_ref' => 'DST-DELHI0000000001', 'state_ref' => 'STE-DL000000000002', 'district_name' => 'Central Delhi'],
    ['district_ref' => 'DST-BLR000000000001', 'state_ref' => 'STE-KA000000000003', 'district_name' => 'Bengaluru Urban'],
    ['district_ref' => 'DST-AHM000000000001', 'state_ref' => 'STE-GJ000000000004', 'district_name' => 'Ahmedabad'],
];

$stmtDist = $pdo->prepare("INSERT INTO districts (district_ref, state_ref, district_name) VALUES (:ref, :sref, :name) ON DUPLICATE KEY UPDATE district_name = VALUES(district_name)");
foreach ($districts as $d) {
    $stmtDist->execute([':ref' => $d['district_ref'], ':sref' => $d['state_ref'], ':name' => $d['district_name']]);
}

$cities = [
    ['city_ref' => 'CTY-MUMBAI00000001', 'district_ref' => 'DST-MUMBAI00000001', 'city_name' => 'Mumbai'],
    ['city_ref' => 'CTY-PUNE0000000002', 'district_ref' => 'DST-PUNE0000000002', 'city_name' => 'Pune'],
    ['city_ref' => 'CTY-DELHI0000000001', 'district_ref' => 'DST-DELHI0000000001', 'city_name' => 'New Delhi'],
    ['city_ref' => 'CTY-BLR000000000001', 'district_ref' => 'DST-BLR000000000001', 'city_name' => 'Bengaluru'],
    ['city_ref' => 'CTY-AHM000000000001', 'district_ref' => 'DST-AHM000000000001', 'city_name' => 'Ahmedabad'],
];

$stmtCity = $pdo->prepare("INSERT INTO cities (city_ref, district_ref, city_name) VALUES (:ref, :dref, :name) ON DUPLICATE KEY UPDATE city_name = VALUES(city_name)");
foreach ($cities as $c) {
    $stmtCity->execute([':ref' => $c['city_ref'], ':dref' => $c['district_ref'], ':name' => $c['city_name']]);
}

$pincodes = [
    ['pincode' => '400001', 'city_ref' => 'CTY-MUMBAI00000001', 'district_ref' => 'DST-MUMBAI00000001', 'state_ref' => 'STE-MH000000000001'],
    ['pincode' => '400002', 'city_ref' => 'CTY-MUMBAI00000001', 'district_ref' => 'DST-MUMBAI00000001', 'state_ref' => 'STE-MH000000000001'],
    ['pincode' => '411001', 'city_ref' => 'CTY-PUNE0000000002', 'district_ref' => 'DST-PUNE0000000002', 'state_ref' => 'STE-MH000000000001'],
    ['pincode' => '110001', 'city_ref' => 'CTY-DELHI0000000001', 'district_ref' => 'DST-DELHI0000000001', 'state_ref' => 'STE-DL000000000002'],
    ['pincode' => '560001', 'city_ref' => 'CTY-BLR000000000001', 'district_ref' => 'DST-BLR000000000001', 'state_ref' => 'STE-KA000000000003'],
    ['pincode' => '380001', 'city_ref' => 'CTY-AHM000000000001', 'district_ref' => 'DST-AHM000000000001', 'state_ref' => 'STE-GJ000000000004'],
];

$stmtPin = $pdo->prepare("INSERT INTO pincodes (pincode, city_ref, district_ref, state_ref) VALUES (:pin, :cref, :dref, :sref) ON DUPLICATE KEY UPDATE state_ref = VALUES(state_ref)");
foreach ($pincodes as $p) {
    $stmtPin->execute([':pin' => $p['pincode'], ':cref' => $p['city_ref'], ':dref' => $p['district_ref'], ':sref' => $p['state_ref']]);
}

echo "Geo seed complete.\n";
