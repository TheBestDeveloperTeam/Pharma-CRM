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
        $this->allowedOrigins = array_map('trim', explode(',', $raw));
    }

    public function handle(Request $request, callable $next): Response
    {
        $origin = $_SERVER['HTTP_ORIGIN'] ?? '';

        if ($request->getMethod() === 'OPTIONS') {
            $response = new Response(null, 204);
            $this->addHeaders($response, $origin);
            return $response;
        }

        $response = $next($request);
        $this->addHeaders($response, $origin);
        return $response;
    }

    private function addHeaders(Response $response, string $origin): void
    {
        if (in_array('*', $this->allowedOrigins, true)) {
            $response->header('Access-Control-Allow-Origin', '*');
        } elseif (in_array($origin, $this->allowedOrigins, true)) {
            $response->header('Access-Control-Allow-Origin', $origin);
        }
        $response->header('Access-Control-Allow-Methods', 'GET, POST, PUT, PATCH, DELETE, OPTIONS');
        $response->header('Access-Control-Allow-Headers', '*');
        $response->header('Access-Control-Max-Age', '86400');
    }
}
