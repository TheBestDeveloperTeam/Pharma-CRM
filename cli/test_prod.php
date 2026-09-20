<?php
$ch = curl_init('https://crm.easysolutins24.in/api/v1/auth/login');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['email'=>'admin@pharmacrm.local', 'password'=>'Admin@1234']));
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json', 'Accept: application/json']);
curl_setopt($ch, CURLOPT_HEADER, true);
$res = curl_exec($ch);
echo $res;
