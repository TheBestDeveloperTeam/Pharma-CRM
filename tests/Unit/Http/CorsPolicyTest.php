<?php

declare(strict_types=1);

namespace Tests\Unit\Http;

use App\Core\Http\CorsPolicy;
use App\Core\Response;
use App\Core\Request;
use App\Http\Middleware\CorsMiddleware;

use PHPUnit\Framework\TestCase;

final class CorsPolicyTest extends TestCase
{
    public static function runStandalone(): void
    {
        $test = new self('test');
        $test->testOriginReflectionAndCredentials();
        $test->testWildcardFallbackWithoutOrigin();
        $test->testAllowedMethodsAndHeaders();
        $test->testCorpAndCoopHeaders();
        $test->testPreflight204Response();
        $test->testMiddlewareInvocation();
        $test->testResponseSendIntegration();
        echo "[PASS] All CorsPolicy unit assertions passed.\n";
    }

    public function testOriginReflectionAndCredentials(): void
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
            $this->assertSame($origin, $headers['Access-Control-Allow-Origin'], "Origin {$origin} must be reflected");
            $this->assertSame('true', $headers['Access-Control-Allow-Credentials'], "Credentials must be true when origin is present");
            $this->assertSame('Origin', $headers['Vary'], "Vary: Origin must be set when origin is reflected");
        }
    }

    public function testWildcardFallbackWithoutOrigin(): void
    {
        $headers = CorsPolicy::getHeaders('');
        $this->assertSame('*', $headers['Access-Control-Allow-Origin'], "Empty origin must fallback to *");
        $this->assertSame('false', $headers['Access-Control-Allow-Credentials'], "Credentials must be false for wildcard *");
    }

    public function testAllowedMethodsAndHeaders(): void
    {
        $headers = CorsPolicy::getHeaders('http://localhost:5173');
        $methods = explode(',', $headers['Access-Control-Allow-Methods']);
        $methods = array_map('trim', $methods);

        foreach (['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS', 'HEAD'] as $method) {
            $this->assertContains($method, $methods, "Method {$method} must be allowed");
        }

        $this->assertStringContainsString('Idempotency-Key', $headers['Access-Control-Allow-Headers'], "Idempotency-Key must be allowed");
        $this->assertStringContainsString('Authorization', $headers['Access-Control-Allow-Headers'], "Authorization must be allowed");
        $this->assertStringContainsString('Content-Type', $headers['Access-Control-Allow-Headers'], "Content-Type must be allowed");
    }

    public function testCorpAndCoopHeaders(): void
    {
        $headers = CorsPolicy::getHeaders('http://localhost:5173');
        $this->assertSame('cross-origin', $headers['Cross-Origin-Resource-Policy'], "CORP must be cross-origin");
        $this->assertSame('unsafe-none', $headers['Cross-Origin-Opener-Policy'], "COOP must be unsafe-none");
    }

    public function testPreflight204Response(): void
    {
        $res = CorsPolicy::preflightResponse('http://localhost:5173');
        $this->assertSame(204, $res->status(), "Preflight must return status 204");
        $this->assertSame('', $res->body(), "Preflight body must be empty");
        $this->assertSame('http://localhost:5173', $res->headers()['Access-Control-Allow-Origin'], "Preflight must contain origin");
    }

    public function testMiddlewareInvocation(): void
    {
        $mw = new CorsMiddleware();
        $_SERVER['REQUEST_METHOD'] = 'OPTIONS';
        $_SERVER['HTTP_ORIGIN'] = 'http://localhost:5173';
        $_SERVER['REQUEST_URI'] = '/api/v1/oauth/token';
        $req = Request::capture();

        $res = $mw($req, fn() => Response::json(['ok' => true]));
        $this->assertSame(204, $res->status(), "OPTIONS request through CorsMiddleware must return 204");
        $this->assertSame('http://localhost:5173', $res->headers()['Access-Control-Allow-Origin'], "Origin header must match");
    }

    public function testResponseSendIntegration(): void
    {
        // Response withCors helper
        $res = Response::json(['message' => 'hello'])->withCors('http://localhost:5173');
        $this->assertSame('http://localhost:5173', $res->headers()['Access-Control-Allow-Origin']);
        $this->assertSame('cross-origin', $res->headers()['Cross-Origin-Resource-Policy']);
    }
}

if (php_sapi_name() === 'cli' && basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'] ?? '')) {
    CorsPolicyTest::runStandalone();
}
