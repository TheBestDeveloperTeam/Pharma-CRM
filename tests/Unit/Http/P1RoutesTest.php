<?php
declare(strict_types=1);

namespace Tests\Unit\Http;

use App\Core\Request;
use App\Core\Router;
use PHPUnit\Framework\TestCase;

final class P1RoutesTest extends TestCase
{
    private Router $router;

    protected function setUp(): void
    {
        parent::setUp();
        $this->router = new Router();
        $routesFn = require __DIR__ . '/../../../bootstrap/routes.php';
        $routesFn($this->router);
    }

    private function assertRouteMatches(string $method, string $path, string $expectedClass, string $expectedMethod, array $expectedParams = []): void
    {
        $request = Request::create($method, $path);
        $match = $this->router->dispatch($request);

        $this->assertNotNull($match, "Route {$method} {$path} must match");
        $this->assertIsArray($match['handler'], "Handler for {$method} {$path} must be [Class, Method]");
        $this->assertSame($expectedClass, $match['handler'][0], "Controller class mismatch for {$method} {$path}");
        $this->assertSame($expectedMethod, $match['handler'][1], "Controller method mismatch for {$method} {$path}");

        foreach ($expectedParams as $key => $val) {
            $this->assertArrayHasKey($key, $match['params'], "Missing param {$key} for {$path}");
            $this->assertSame($val, $match['params'][$key], "Param value mismatch for {$key} on {$path}");
        }
    }

    public function testOrdersSubResourcesAndStaticRoutes(): void
    {
        $ordClass = \App\Http\Controllers\Api\V1\Admin\OrdersController::class;

        $this->assertRouteMatches('GET', '/api/v1/admin/orders/pending-dispatch', $ordClass, 'pendingDispatch');
        $this->assertRouteMatches('GET', '/api/v1/admin/orders/pending-billing', $ordClass, 'pendingBilling');
        $this->assertRouteMatches('GET', '/api/v1/admin/orders/blocked', $ordClass, 'blocked');
        $this->assertRouteMatches('GET', '/api/v1/admin/orders', $ordClass, 'index');
        $this->assertRouteMatches('POST', '/api/v1/admin/orders', $ordClass, 'store');
        $this->assertRouteMatches('POST', '/api/v1/admin/orders/calculate', $ordClass, 'calculate');
        $this->assertRouteMatches('GET', '/api/v1/admin/orders/ORD-001', $ordClass, 'show', ['ref' => 'ORD-001']);
        $this->assertRouteMatches('GET', '/api/v1/admin/orders/ORD-001/items', $ordClass, 'items', ['ref' => 'ORD-001']);
        $this->assertRouteMatches('GET', '/api/v1/admin/orders/ORD-001/timeline', $ordClass, 'timeline', ['ref' => 'ORD-001']);
        $this->assertRouteMatches('GET', '/api/v1/admin/orders/ORD-001/invoices', $ordClass, 'invoices', ['ref' => 'ORD-001']);
        $this->assertRouteMatches('GET', '/api/v1/admin/orders/ORD-001/dispatches', $ordClass, 'dispatches', ['ref' => 'ORD-001']);
        $this->assertRouteMatches('GET', '/api/v1/admin/orders/ORD-001/payments', $ordClass, 'payments', ['ref' => 'ORD-001']);
        $this->assertRouteMatches('GET', '/api/v1/admin/orders/ORD-001/reservations', $ordClass, 'reservations', ['ref' => 'ORD-001']);
        $this->assertRouteMatches('POST', '/api/v1/admin/orders/ORD-001/reopen', $ordClass, 'reopen', ['ref' => 'ORD-001']);
    }

    public function testDispatchesRoutes(): void
    {
        $dspClass = \App\Http\Controllers\Api\V1\Admin\DispatchesController::class;

        $this->assertRouteMatches('GET', '/api/v1/admin/dispatches/pending', $dspClass, 'pending');
        $this->assertRouteMatches('GET', '/api/v1/admin/dispatches', $dspClass, 'index');
        $this->assertRouteMatches('POST', '/api/v1/admin/dispatches', $dspClass, 'store');
        $this->assertRouteMatches('GET', '/api/v1/admin/dispatches/DSP-001', $dspClass, 'show', ['ref' => 'DSP-001']);
        $this->assertRouteMatches('PATCH', '/api/v1/admin/dispatches/DSP-001', $dspClass, 'update', ['ref' => 'DSP-001']);
        $this->assertRouteMatches('POST', '/api/v1/admin/dispatches/DSP-001/packed', $dspClass, 'packed', ['ref' => 'DSP-001']);
        $this->assertRouteMatches('POST', '/api/v1/admin/dispatches/DSP-001/ship', $dspClass, 'ship', ['ref' => 'DSP-001']);
        $this->assertRouteMatches('POST', '/api/v1/admin/dispatches/DSP-001/in-transit', $dspClass, 'inTransit', ['ref' => 'DSP-001']);
        $this->assertRouteMatches('PATCH', '/api/v1/admin/dispatches/DSP-001/lr', $dspClass, 'updateLr', ['ref' => 'DSP-001']);
        $this->assertRouteMatches('POST', '/api/v1/admin/dispatches/DSP-001/deliver', $dspClass, 'deliver', ['ref' => 'DSP-001']);
        $this->assertRouteMatches('GET', '/api/v1/admin/dispatches/DSP-001/timeline', $dspClass, 'timeline', ['ref' => 'DSP-001']);
    }

    public function testInvoicesSubResourceRoutes(): void
    {
        $invClass = \App\Http\Controllers\Api\V1\Admin\InvoicesController::class;

        $this->assertRouteMatches('GET', '/api/v1/admin/invoices/INV-001/items', $invClass, 'items', ['ref' => 'INV-001']);
        $this->assertRouteMatches('GET', '/api/v1/admin/invoices/INV-001/payments', $invClass, 'payments', ['ref' => 'INV-001']);
        $this->assertRouteMatches('GET', '/api/v1/admin/invoices/INV-001/dispatch', $invClass, 'dispatch', ['ref' => 'INV-001']);
    }

    public function testPricingEnhancedRoutes(): void
    {
        $prcClass = \App\Http\Controllers\Api\V1\Admin\PricesController::class;

        $this->assertRouteMatches('PATCH', '/api/v1/admin/prices/PRC-001', $prcClass, 'update', ['ref' => 'PRC-001']);
        $this->assertRouteMatches('DELETE', '/api/v1/admin/prices/PRC-001', $prcClass, 'delete', ['ref' => 'PRC-001']);
        $this->assertRouteMatches('GET', '/api/v1/admin/prices/PRC-001/history', $prcClass, 'history', ['ref' => 'PRC-001']);
    }

    public function testSchemesEnhancedRoutes(): void
    {
        $schClass = \App\Http\Controllers\Api\V1\Admin\SchemesController::class;

        $this->assertRouteMatches('GET', '/api/v1/admin/schemes/active', $schClass, 'active');
        $this->assertRouteMatches('POST', '/api/v1/admin/schemes/calculate', $schClass, 'calculate');
        $this->assertRouteMatches('GET', '/api/v1/admin/schemes', $schClass, 'index');
        $this->assertRouteMatches('DELETE', '/api/v1/admin/schemes/SCH-001', $schClass, 'delete', ['ref' => 'SCH-001']);
        $this->assertRouteMatches('POST', '/api/v1/admin/schemes/SCH-001/clone', $schClass, 'cloneScheme', ['ref' => 'SCH-001']);
    }

    public function testOnboardingEnhancedRoutes(): void
    {
        $pubOnbClass = \App\Http\Controllers\Api\V1\PublicOnboardingController::class;
        $admOnbClass = \App\Http\Controllers\Api\V1\Admin\OnboardingController::class;

        $this->assertRouteMatches('GET', '/api/v1/onboarding/validate', $pubOnbClass, 'validateToken');
        $this->assertRouteMatches('GET', '/api/v1/onboarding/invite-context', $pubOnbClass, 'inviteContext');
        $this->assertRouteMatches('POST', '/api/v1/onboarding/register', $pubOnbClass, 'register');

        $this->assertRouteMatches('POST', '/api/v1/admin/onboarding/invites/INV-001/resend', $admOnbClass, 'resend', ['ref' => 'INV-001']);
        $this->assertRouteMatches('POST', '/api/v1/admin/onboarding/invites/INV-001/revoke', $admOnbClass, 'revoke', ['ref' => 'INV-001']);
        $this->assertRouteMatches('GET', '/api/v1/admin/onboarding/ONB-001/documents', $admOnbClass, 'documents', ['ref' => 'ONB-001']);
        $this->assertRouteMatches('GET', '/api/v1/admin/onboarding/ONB-001/timeline', $admOnbClass, 'timeline', ['ref' => 'ONB-001']);
    }

    public function testRolesEnhancedRoutes(): void
    {
        $authClass = \App\Http\Controllers\Api\V1\Admin\AuthorizationController::class;

        $this->assertRouteMatches('GET', '/api/v1/admin/roles/matrix', $authClass, 'matrix');
        $this->assertRouteMatches('POST', '/api/v1/admin/roles/ROL-001/activate', $authClass, 'activate', ['ref' => 'ROL-001']);
        $this->assertRouteMatches('POST', '/api/v1/admin/roles/ROL-001/deactivate', $authClass, 'deactivate', ['ref' => 'ROL-001']);
        $this->assertRouteMatches('GET', '/api/v1/admin/roles/ROL-001/users', $authClass, 'roleUsers', ['ref' => 'ROL-001']);
    }
}
