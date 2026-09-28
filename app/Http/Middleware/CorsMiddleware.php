<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Core\Request;
use App\Core\Response;
use App\Core\Http\CorsPolicy;

/**
 * CorsMiddleware — Seamless pipeline middleware utilizing CorsPolicy.
 * Handles preflight OPTIONS requests and guarantees cross-origin policy headers.
 */
class CorsMiddleware
{
    public function __invoke(Request $request, callable $next): Response
    {
        $origin = $request->origin();

        if ($request->isOptions()) {
            return CorsPolicy::preflightResponse($origin);
        }

        $response = $next($request);
        return CorsPolicy::apply($response, $origin);
    }

    /**
     * Backward-compatible helper for explicit header application.
     */
    public function addHeaders(Response $response, string $origin): Response
    {
        return CorsPolicy::apply($response, $origin);
    }
}
