<?php

declare(strict_types=1);

namespace App\Core\Http;

use App\Core\Response;

/**
 * CorsPolicy — Enterprise Unified Cross-Origin & Security Policy Layer.
 *
 * Centralized authority for:
 * - Dynamic Origin resolution & credentials handling (avoiding wildcard with credentials issue)
 * - HTTP Methods allowance (GET, POST, PUT, PATCH, DELETE, OPTIONS, HEAD)
 * - Headers allowance & exposure (Idempotency-Key, Request-ID, Auth, etc.)
 * - Cross-Origin Resource Policy (CORP: cross-origin)
 * - Cross-Origin Opener Policy (COOP: unsafe-none)
 * - Preflight caching (Max-Age: 86400)
 */
final class CorsPolicy
{
    public const ALLOWED_METHODS = 'GET, POST, PUT, PATCH, DELETE, OPTIONS, HEAD';

    public const ALLOWED_HEADERS = 'Authorization, Content-Type, Accept, Origin, X-Requested-With, '
        . 'X-Request-ID, Idempotency-Key, X-Franchise-Ref, X-Org-Ref, Cache-Control, Pragma, '
        . 'X-Client-Version, If-Match, If-None-Match, *';

    public const EXPOSED_HEADERS = 'X-Request-ID, Idempotency-Replay, Content-Disposition, '
        . 'Content-Length, ETag, *';

    public const MAX_AGE = 86400;

    /**
     * Resolve incoming request Origin.
     */
    public static function resolveOrigin(?string $origin = null): string
    {
        if ($origin !== null && trim($origin) !== '') {
            return trim($origin);
        }

        $superOrigin = $_SERVER['HTTP_ORIGIN'] ?? '';
        if (trim($superOrigin) !== '') {
            return trim($superOrigin);
        }

        return '';
    }

    /**
     * Generate the complete universal CORS and CORP headers dictionary.
     *
     * @return array<string, string>
     */
    public static function getHeaders(?string $origin = null): array
    {
        $resolvedOrigin = self::resolveOrigin($origin);

        // When client provides an Origin header (e.g. http://localhost:5173), reflect it
        // dynamically so Access-Control-Allow-Credentials: true is accepted by browsers.
        // If no Origin header was provided, fallback to wildcard *.
        $allowOrigin = $resolvedOrigin !== '' ? $resolvedOrigin : '*';
        $allowCredentials = ($allowOrigin !== '*') ? 'true' : 'false';

        $headers = [
            'Access-Control-Allow-Origin'      => $allowOrigin,
            'Access-Control-Allow-Credentials' => $allowCredentials,
            'Access-Control-Allow-Methods'     => self::ALLOWED_METHODS,
            'Access-Control-Allow-Headers'     => self::ALLOWED_HEADERS,
            'Access-Control-Expose-Headers'    => self::EXPOSED_HEADERS,
            'Access-Control-Max-Age'           => (string)self::MAX_AGE,
            'Cross-Origin-Resource-Policy'     => 'cross-origin',
            'Cross-Origin-Opener-Policy'       => 'unsafe-none',
        ];

        if ($allowOrigin !== '*') {
            $headers['Vary'] = 'Origin';
        }

        return $headers;
    }

    /**
     * Apply universal CORS headers to an App\Core\Response instance.
     */
    public static function apply(Response $response, ?string $origin = null): Response
    {
        $headers = self::getHeaders($origin);
        foreach ($headers as $name => $value) {
            $response = $response->withHeader($name, $value);
        }
        return $response;
    }

    /**
     * Emit native HTTP headers directly (used in fast-exit entry points like public/index.php).
     */
    public static function emitNativeHeaders(?string $origin = null): void
    {
        if (headers_sent()) {
            return;
        }

        foreach (self::getHeaders($origin) as $name => $value) {
            header("{$name}: {$value}", replace: true);
        }
    }

    /**
     * Build an immediate 204 No Content preflight response.
     */
    public static function preflightResponse(?string $origin = null): Response
    {
        $response = Response::empty(204);
        return self::apply($response, $origin);
    }
}
