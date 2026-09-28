<?php
declare(strict_types=1);

/**
 * Universal CORS Pre-Handling
 * Allow all origins (including any port on localhost, IP, or domain),
 * all HTTP methods, credentials, and common custom headers.
 */
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if ($origin !== '') {
    header("Access-Control-Allow-Origin: {$origin}");
    header("Access-Control-Allow-Credentials: true");
    header("Vary: Origin");
} else {
    header("Access-Control-Allow-Origin: *");
}

header("Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS, HEAD");
header("Access-Control-Allow-Headers: Authorization, Content-Type, Accept, Origin, X-Requested-With, X-Request-ID, Idempotency-Key, X-Franchise-Ref, X-Org-Ref, Cache-Control, Pragma, *");
header("Access-Control-Expose-Headers: X-Request-ID, Idempotency-Replay, Content-Disposition, *");
header("Access-Control-Max-Age: 86400");
header("Cross-Origin-Resource-Policy: cross-origin");
header("Cross-Origin-Opener-Policy: unsafe-none");

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
