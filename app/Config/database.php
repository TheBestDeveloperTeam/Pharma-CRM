<?php
declare(strict_types=1);
use function App\Support\env;

return [
    'host'     => env('DB_HOST', 'localhost'),
    'port'     => env('DB_PORT', '3306'),
    'name'     => env('DB_NAME', 'crm'),
    'user'     => env('DB_USER', 'root'),
    'password' => env('DB_PASSWORD', ''),
];
