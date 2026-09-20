<?php
declare(strict_types=1);
use function App\Support\env;

return [
    'iss'          => 'pharma-crm',
    'access_ttl'   => (int) env('JWT_ACCESS_TTL', '900'),
    'refresh_abs'  => 1209600, // 14 days
    'refresh_idle' => 28800,   // 8 hours
    'active_kid'   => 'k1',
    'keys'         => [
        'k1' => env('JWT_KEY_K1', ''),
        'k0' => env('JWT_KEY_K0', ''),
    ],
    'clients'      => [
        'crm-super'  => ['aud' => 'super',  'roles' => ['SUPER_ADMIN']],
        'crm-admin'  => ['aud' => 'admin',  'roles' => ['FRANCHISE_ADMIN']],
        'crm-sales'  => ['aud' => 'sales',  'roles' => ['SALES']],
        'crm-portal' => ['aud' => 'portal', 'roles' => ['DISTRIBUTOR']],
    ],
];
