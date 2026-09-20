<?php
declare(strict_types=1);
use function App\Support\env;

return [
    'env'      => env('APP_ENV', 'production'),
    'debug'    => env('APP_DEBUG', 'false') === 'true',
    'url'      => env('APP_URL', 'https://crm.example.com'),
    'timezone' => env('APP_TIMEZONE', 'Asia/Kolkata'),
    'key'      => env('APP_KEY', ''),
    'storage'  => (function() {
        $path = env('STORAGE_PATH', 'storage');
        // If absolute (Unix / or Windows C:\) return as is, else prepend root
        return (str_starts_with($path, '/') || preg_match('/^[A-Z]:\\\\/i', $path))
            ? $path
            : realpath(__DIR__ . '/../../') . '/' . $path;
    })(),
    'log_level'=> env('LOG_LEVEL', 'warning'),
    'name'     => 'Pharma CRM',
    'version'  => '3.0',
];
