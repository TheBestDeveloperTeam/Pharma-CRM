<?php

declare(strict_types=1);

namespace Tests\Unit\Http;

use App\Core\Http\CorsPolicy;
use App\Core\Response;
use App\Core\Request;
use App\Http\Middleware\CorsMiddleware;

require_once __DIR__ . '/../../../bootstrap/autoload.php';

final class CorsPolicyTest
{
    public static function run(): void
    {
        self::testOriginReflectionAndCredentials();
        self::testWildcardFallbackWithoutOrigin();
        self::testAllowedMethodsAndHeaders();
        self::testCorpAndCoopHeaders();
        self::testPreflight204Response();
        self::testMiddlewareInvocation();
        self::testResponseSendIntegration();
        echo "[PASS] All CorsPolicy unit assertions passed.\n";
    }

    private static function testOriginReflectionAndCredentials(): void
    {
        $testOrigins = [
            'http://localhost:5173',
            'http://localhost:3000',
            'http://127.0.0.1:8080',
            'https://admin.example.com',
            'https://crm.easysolutins24.in',
        ];

        foreach ($testOrigins as $origin) {
            $headers = CorsPolicy::getHeaders($origin);
            assert($headers['Access-Control-Allow-Origin'] === $origin, "Origin {$origin} must be reflected");
            assert($headers['Access-Control-Allow-Credentials'] === 'true', "Credentials must be true when origin is present");
            assert($headers['Vary'] === 'Origin', "Vary: Origin must be set when origin is reflected");
        }
    }

    private static function testWildcardFallbackWithoutOrigin(): void
    {
        $headers = CorsPolicy::getHeaders('');
        assert($headers['Access-Control-Allow-Origin'] === '*', "Empty origin must fallback to *");
        assert($headers['Access-Control-Allow-Credentials'] === 'false', "Credentials must be false for wildcard *");
    }

    private static function testAllowedMethodsAndHeaders(): void
    {
        $headers = CorsPolicy::getHeaders('http://localhost:5173');
        $methods = explode(',', $headers['Access-Control-Allow-Methods']);
        $methods = array_map('trim', $methods);

        foreach (['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS', 'HEAD'] as $method) {
            assert(in_array($method, $methods, true), "Method {$method} must be allowed");
        }

        assert(str_contains($headers['Access-Control-Allow-Headers'], 'Idempotency-Key'), "Idempotency-Key must be allowed");
        assert(str_contains($headers['Access-Control-Allow-Headers'], 'Authorization'), "Authorization must be allowed");
        assert(str_contains($headers['Access-Control-Allow-Headers'], 'Content-Type'), "Content-Type must be allowed");
    }

    private static function testCorpAndCoopHeaders(): void
    {
        $headers = CorsPolicy::getHeaders('http://localhost:5173');
        assert($headers['Cross-Origin-Resource-Policy'] === 'cross-origin', "CORP must be cross-origin");
        assert($headers['Cross-Origin-Opener-Policy'] === 'unsafe-none', "COOP must be unsafe-none");
    }

    private static function testPreflight204Response(): void
    {
        $res = CorsPolicy::preflightResponse('http://localhost:5173');
        assert($res->status() === 204, "Preflight must return status 204");
        assert($res->body() === '', "Preflight body must be empty");
        assert($res->headers()['Access-Control-Allow-Origin'] === 'http://localhost:5173', "Preflight must contain origin");
    }

    private static function testMiddlewareInvocation(): void
    {
        $mw = new CorsMiddleware();
        $_SERVER['REQUEST_METHOD'] = 'OPTIONS';
        $_SERVER['HTTP_ORIGIN'] = 'http://localhost:5173';
        $_SERVER['REQUEST_URI'] = '/api/v1/oauth/token';
        $req = Request::capture();

        $res = $mw($req, fn() => Response::json(['ok' => true]));
        assert($res->status() === 204, "OPTIONS request through CorsMiddleware must return 204");
        assert($res->headers()['Access-Control-Allow-Origin'] === 'http://localhost:5173', "Origin header must match");
    }

    private static function testResponseSendIntegration(): void
    {
        // Response withCors helper
        $res = Response::json(['message' => 'hello'])->withCors('http://localhost:5173');
        assert($res->headers()['Access-Control-Allow-Origin'] === 'http://localhost:5173');
        assert($res->headers()['Cross-Origin-Resource-Policy'] === 'cross-origin');
    }
}

if (php_sapi_name() === 'cli' && basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'] ?? '')) {
    CorsPolicyTest::run();
}
