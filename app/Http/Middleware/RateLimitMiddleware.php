<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Core\Request;
use App\Core\Response;
use App\Core\Cache;

/**
 * RateLimitMiddleware — IP-based sliding window rate limiter.
 */
class RateLimitMiddleware
{
    private int $maxRequests;
    private int $windowSeconds;

    public function __construct(private readonly Cache $cache)
    {
        $this->maxRequests   = 60;
        $this->windowSeconds = 60;
    }

    public function handle(Request $request, callable $next): Response
    {
        $key   = 'rate_' . $request->getIp();
        $count = (int) $this->cache->get($key, 0);

        if ($count >= $this->maxRequests) {
            return Response::json([
                'success' => false,
                'error'   => ['code' => 'RATE_LIMIT_EXCEEDED', 'message' => 'Too many requests.'],
                'meta'    => ['request_id' => $request->getId()],
            ], 429);
        }

        $this->cache->set($key, $count + 1, $count === 0 ? $this->windowSeconds : 0);

        $response = $next($request);
        $response->header('X-RateLimit-Limit', (string) $this->maxRequests);
        $response->header('X-RateLimit-Remaining', (string) max(0, $this->maxRequests - $count - 1));

        return $response;
    }
}
