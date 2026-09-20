<?php
declare(strict_types=1);
namespace App\Http\Middleware;

use App\Core\{Request, Response};

final class SecurityHeaders
{
    private const CSP = "default-src 'self'; script-src 'self'; style-src 'self'; " .
                        "img-src 'self' data:; font-src 'self'; connect-src 'self'; " .
                        "object-src 'none'; base-uri 'none'; form-action 'self'; frame-ancestors 'none'";

    public function __invoke(Request $r, callable $next): Response
    {
        $response = $next($r);

        return $response
            ->withHeader('Content-Security-Policy', self::CSP)
            ->withHeader('Strict-Transport-Security', 'max-age=31536000; includeSubDomains')
            ->withHeader('X-Content-Type-Options', 'nosniff')
            ->withHeader('Referrer-Policy', 'no-referrer')
            ->withHeader('Permissions-Policy', 'geolocation=(), camera=(), microphone=()')
            ->withHeader('Cache-Control', str_starts_with($r->path, '/api/') || str_starts_with($r->path, '/oauth/')
                ? 'no-store, no-cache, must-revalidate'
                : 'no-store');
    }
}
