<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Core\Request;
use App\Core\Response;

/**
 * CorsMiddleware — Sets CORS headers and handles preflight OPTIONS requests.
 * Universally permits all origins, all ports (including localhost), all HTTP methods,
 * and credentials support.
 */
class CorsMiddleware
{
    private array $allowedOrigins;

    public function __construct()
    {
        $raw = env('CORS_ALLOWED_ORIGINS', '*');
        $this->allowedOrigins = array_filter(array_map('trim', explode(',', (string)$raw)));
        if (empty($this->allowedOrigins)) {
            $this->allowedOrigins = ['*'];
        }
    }

    public function __invoke(Request $request, callable $next): Response
    {
        $origin = $request->header('origin');

        if ($request->method === 'OPTIONS') {
            $response = Response::empty(204);
            return $this->addHeaders($response, $origin);
        }

        $response = $next($request);
        return $this->addHeaders($response, $origin);
    }

    public function addHeaders(Response $response, string $origin): Response
    {
        // When an Origin header is provided by the client, reflect that origin and allow credentials.
        // This solves browser restrictions where Access-Control-Allow-Origin cannot be '*' when credentials are true.
        $allowOrigin = $origin !== '' ? $origin : '*';
        $allowCredentials = ($allowOrigin !== '*') ? 'true' : 'false';

        return $response
            ->withHeader('Access-Control-Allow-Origin', $allowOrigin)
            ->withHeader('Access-Control-Allow-Credentials', $allowCredentials)
            ->withHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, PATCH, DELETE, OPTIONS, HEAD')
            ->withHeader('Access-Control-Allow-Headers', 'Authorization, Content-Type, Accept, Origin, X-Requested-With, X-Request-ID, Idempotency-Key, X-Franchise-Ref, X-Org-Ref, Cache-Control, Pragma, *')
            ->withHeader('Access-Control-Expose-Headers', 'X-Request-ID, Idempotency-Replay, Content-Disposition, *')
            ->withHeader('Access-Control-Max-Age', '86400')
            ->withHeader('Cross-Origin-Resource-Policy', 'cross-origin')
            ->withHeader('Cross-Origin-Opener-Policy', 'unsafe-none')
            ->withHeader('Vary', 'Origin');
    }
}
