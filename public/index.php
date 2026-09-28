<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/Core/Response.php';
require_once __DIR__ . '/../app/Core/Http/CorsPolicy.php';

// Universal CORS & CORP Pre-Handling across all origins, ports, and methods
\App\Core\Http\CorsPolicy::emitNativeHeaders();

// Fast exit for preflight OPTIONS requests before bootstrapping application
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
    http_response_code(204);
    header("Content-Length: 0");
    exit(0);
}

/**
 * Front Controller & Routing Engine
 */

// Basic error reporting
error_reporting(E_ALL);
ini_set('display_errors', '1');

// Bootstrap the application
require_once __DIR__ . '/../bootstrap/app.php';
