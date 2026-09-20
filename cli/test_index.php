<?php
$urls = [
    'https://crm.easysolutins24.in/assets/js/api.js',
];
foreach($urls as $url) {
    echo "Testing $url\n";
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HEADER, true);
    $res = curl_exec($ch);
    echo explode("\r\n\r\n", $res)[0] . "\n\n";
}
