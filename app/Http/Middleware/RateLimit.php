<?php
declare(strict_types=1);
namespace App\Http\Middleware;

use App\Core\{Request, Response};
use App\Core\Security\RateLimiter;

final class RateLimit
{
    public function __construct(private RateLimiter $limiter) {}

    public function __invoke(Request $r, callable $next): Response
    {
        // Global IP rate limit for all API requests (240 requests / minute)
        if (str_starts_with($r->path, '/api/')) {
            $this->limiter->hit('api-ip', $r->clientIp(), 240, 1);
        }

        return $next($r);
    }
}
