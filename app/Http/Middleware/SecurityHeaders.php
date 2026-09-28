<?php
declare(strict_types=1);
namespace App\Http\Middleware;

use App\Core\{Request, Response};

final class SecurityHeaders
{
    public function __invoke(Request $r, callable $next): Response
    {
        $response = $next($r);

        return $response
            ->withHeader('Cross-Origin-Resource-Policy', 'cross-origin')
            ->withHeader('Cross-Origin-Opener-Policy', 'unsafe-none')
            ->withHeader('Strict-Transport-Security', 'max-age=31536000; includeSubDomains')
            ->withHeader('X-Content-Type-Options', 'nosniff')
            ->withHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
            ->withHeader('Permissions-Policy', 'geolocation=(), camera=(), microphone=()')
            ->withHeader('Cache-Control', str_starts_with($r->path, '/api/') || str_starts_with($r->path, '/oauth/')
                ? 'no-store, no-cache, must-revalidate'
                : 'no-store');
    }
}
