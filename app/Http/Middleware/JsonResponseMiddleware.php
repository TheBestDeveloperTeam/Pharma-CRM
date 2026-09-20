<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Core\Request;
use App\Core\Response;

/**
 * JsonResponseMiddleware — Ensures all responses have correct Content-Type.
 */
class JsonResponseMiddleware
{
    public function handle(Request $request, callable $next): Response
    {
        $response = $next($request);
        $response->header('Content-Type', 'application/json; charset=utf-8');
        return $response;
    }
}
