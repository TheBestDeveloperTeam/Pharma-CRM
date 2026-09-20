<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Core\Request;
use App\Core\Response;
use App\Core\Logger;

/**
 * RequestLogMiddleware — Logs every request with timing and status.
 */
class RequestLogMiddleware
{
    public function __construct(private readonly Logger $logger) {}

    public function handle(Request $request, callable $next): Response
    {
        $start = microtime(true);

        $response = $next($request);

        $duration = round((microtime(true) - $start) * 1000, 2);

        $this->logger->info('HTTP Request', [
            'request_id' => $request->getId(),
            'method'     => $request->getMethod(),
            'uri'        => $request->getUri(),
            'status'     => $response->getStatus(),
            'duration_ms'=> $duration,
            'ip'         => $request->getIp(),
            'user_agent' => $request->getUserAgent(),
        ]);

        // Attach request ID to response
        $response->header('X-Request-ID', $request->getId());

        return $response;
    }
}
