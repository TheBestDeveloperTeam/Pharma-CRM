<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Core\Request;
use App\Core\Response;

/**
 * CorsMiddleware — Sets CORS headers and handles preflight OPTIONS requests.
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

    private function addHeaders(Response $response, string $origin): Response
    {
        $allowOrigin = '*';
        if (in_array('*', $this->allowedOrigins, true)) {
            $allowOrigin = '*';
        } elseif ($origin !== '' && in_array($origin, $this->allowedOrigins, true)) {
            $allowOrigin = $origin;
        } elseif (!empty($this->allowedOrigins)) {
            $allowOrigin = $this->allowedOrigins[0];
        }

        return $response
            ->withHeader('Access-Control-Allow-Origin', $allowOrigin)
            ->withHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, PATCH, DELETE, OPTIONS')
            ->withHeader('Access-Control-Allow-Headers', 'Authorization, Content-Type, Accept, Origin, X-Requested-With, X-Request-ID, Idempotency-Key, X-Franchise-Ref, X-Org-Ref')
            ->withHeader('Access-Control-Expose-Headers', 'X-Request-ID, Idempotency-Replay, Content-Disposition')
            ->withHeader('Access-Control-Max-Age', '86400');
    }
}
