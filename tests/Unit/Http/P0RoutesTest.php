<?php
declare(strict_types=1);

namespace Tests\Unit\Http;

use App\Core\Request;
use App\Core\Router;
use PHPUnit\Framework\TestCase;

final class P0RoutesTest extends TestCase
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

    public function testGeoCascadingRoutes(): void
    {
        $geoClass = \App\Http\Controllers\Api\V1\GeoController::class;

        $this->assertRouteMatches('GET', '/api/v1/geo/states', $geoClass, 'states');
        $this->assertRouteMatches('GET', '/api/v1/geo/states/STA-001/districts', $geoClass, 'districtsByState', ['state_ref' => 'STA-001']);
        $this->assertRouteMatches('GET', '/api/v1/geo/districts', $geoClass, 'districts');
        $this->assertRouteMatches('GET', '/api/v1/geo/districts/DST-001/cities', $geoClass, 'citiesByDistrict', ['district_ref' => 'DST-001']);
        $this->assertRouteMatches('GET', '/api/v1/geo/cities', $geoClass, 'cities');
        $this->assertRouteMatches('GET', '/api/v1/geo/cities/CTY-001/pincodes', $geoClass, 'pincodesByCity', ['city_ref' => 'CTY-001']);
        $this->assertRouteMatches('GET', '/api/v1/geo/pincodes', $geoClass, 'pincodes');
        $this->assertRouteMatches('GET', '/api/v1/geo/pincodes/400001', $geoClass, 'pincode', ['pin' => '400001']);
        $this->assertRouteMatches('GET', '/api/v1/geo/search', $geoClass, 'search');
    }

    public function testAdminGeoCrudRoutes(): void
    {
        $geoClass = \App\Http\Controllers\Api\V1\GeoController::class;

        $this->assertRouteMatches('POST', '/api/v1/admin/geo/states', $geoClass, 'createState');
        $this->assertRouteMatches('GET', '/api/v1/admin/geo/states/STA-001', $geoClass, 'showState', ['ref' => 'STA-001']);
        $this->assertRouteMatches('PATCH', '/api/v1/admin/geo/states/STA-001', $geoClass, 'updateState', ['ref' => 'STA-001']);
        $this->assertRouteMatches('DELETE', '/api/v1/admin/geo/states/STA-001', $geoClass, 'deleteState', ['ref' => 'STA-001']);

        $this->assertRouteMatches('POST', '/api/v1/admin/geo/districts', $geoClass, 'createDistrict');
        $this->assertRouteMatches('GET', '/api/v1/admin/geo/districts/DST-001', $geoClass, 'showDistrict', ['ref' => 'DST-001']);
        $this->assertRouteMatches('PATCH', '/api/v1/admin/geo/districts/DST-001', $geoClass, 'updateDistrict', ['ref' => 'DST-001']);
        $this->assertRouteMatches('DELETE', '/api/v1/admin/geo/districts/DST-001', $geoClass, 'deleteDistrict', ['ref' => 'DST-001']);

        $this->assertRouteMatches('POST', '/api/v1/admin/geo/cities', $geoClass, 'createCity');
        $this->assertRouteMatches('GET', '/api/v1/admin/geo/cities/CTY-001', $geoClass, 'showCity', ['ref' => 'CTY-001']);
        $this->assertRouteMatches('PATCH', '/api/v1/admin/geo/cities/CTY-001', $geoClass, 'updateCity', ['ref' => 'CTY-001']);
        $this->assertRouteMatches('DELETE', '/api/v1/admin/geo/cities/CTY-001', $geoClass, 'deleteCity', ['ref' => 'CTY-001']);

        $this->assertRouteMatches('POST', '/api/v1/admin/geo/pincodes', $geoClass, 'createPincode');
        $this->assertRouteMatches('PATCH', '/api/v1/admin/geo/pincodes/400001', $geoClass, 'updatePincode', ['ref' => '400001']);
        $this->assertRouteMatches('DELETE', '/api/v1/admin/geo/pincodes/400001', $geoClass, 'deletePincode', ['ref' => '400001']);
    }

    public function testEffectivePermissionsRoute(): void
    {
        $authClass = \App\Http\Controllers\Api\V1\AuthController::class;
        $this->assertRouteMatches('GET', '/api/v1/auth/effective-permissions', $authClass, 'effectivePermissions');
    }

    public function testDropdownRoutes(): void
    {
        $this->assertRouteMatches('GET', '/api/v1/admin/users/dropdown', \App\Http\Controllers\Api\V1\Admin\UsersController::class, 'dropdown');
        $this->assertRouteMatches('GET', '/api/v1/admin/parties/dropdown', \App\Http\Controllers\Api\V1\Admin\PartiesController::class, 'dropdown');
        $this->assertRouteMatches('GET', '/api/v1/admin/products/dropdown', \App\Http\Controllers\Api\V1\Admin\ProductsController::class, 'dropdown');
    }

    public function testLeadWorkflowRoutes(): void
    {
        $leadClass = \App\Http\Controllers\Api\V1\Admin\LeadsController::class;

        $this->assertRouteMatches('GET', '/api/v1/admin/leads/check-duplicate', $leadClass, 'duplicateCheck');
        $this->assertRouteMatches('POST', '/api/v1/admin/leads/bulk-assign', $leadClass, 'bulkAssign');
        $this->assertRouteMatches('POST', '/api/v1/admin/leads/LED-100/convert', $leadClass, 'convert', ['ref' => 'LED-100']);
        $this->assertRouteMatches('GET', '/api/v1/admin/leads/LED-100/remarks', $leadClass, 'remarks', ['ref' => 'LED-100']);
        $this->assertRouteMatches('POST', '/api/v1/admin/leads/LED-100/remarks', $leadClass, 'addRemark', ['ref' => 'LED-100']);
        $this->assertRouteMatches('GET', '/api/v1/admin/leads/LED-100/timeline', $leadClass, 'timeline', ['ref' => 'LED-100']);
        $this->assertRouteMatches('GET', '/api/v1/admin/leads/LED-100/follow-ups', $leadClass, 'followUps', ['ref' => 'LED-100']);
    }

    public function testFollowUpRoutes(): void
    {
        $fuClass = \App\Http\Controllers\Api\V1\Admin\FollowUpsController::class;

        $this->assertRouteMatches('GET', '/api/v1/admin/follow-ups/history', $fuClass, 'history');
        $this->assertRouteMatches('GET', '/api/v1/admin/follow-ups/FUP-100', $fuClass, 'show', ['ref' => 'FUP-100']);
        $this->assertRouteMatches('PATCH', '/api/v1/admin/follow-ups/FUP-100', $fuClass, 'update', ['ref' => 'FUP-100']);
        $this->assertRouteMatches('POST', '/api/v1/admin/follow-ups/FUP-100/cancel', $fuClass, 'cancel', ['ref' => 'FUP-100']);
        $this->assertRouteMatches('GET', '/api/v1/admin/follow-ups/FUP-100/remarks', $fuClass, 'remarks', ['ref' => 'FUP-100']);
        $this->assertRouteMatches('POST', '/api/v1/admin/follow-ups/FUP-100/remarks', $fuClass, 'addRemark', ['ref' => 'FUP-100']);
    }

    public function testPartySubRoutes(): void
    {
        $partyClass = \App\Http\Controllers\Api\V1\Admin\PartiesController::class;

        $this->assertRouteMatches('GET', '/api/v1/admin/parties/PTY-100/orders', $partyClass, 'orders', ['ref' => 'PTY-100']);
        $this->assertRouteMatches('GET', '/api/v1/admin/parties/PTY-100/invoices', $partyClass, 'invoices', ['ref' => 'PTY-100']);
        $this->assertRouteMatches('GET', '/api/v1/admin/parties/PTY-100/payments', $partyClass, 'payments', ['ref' => 'PTY-100']);
        $this->assertRouteMatches('POST', '/api/v1/admin/parties/PTY-100/credit-limit', $partyClass, 'updateCreditLimit', ['ref' => 'PTY-100']);
        $this->assertRouteMatches('POST', '/api/v1/admin/parties/PTY-100/suspend', $partyClass, 'suspend', ['ref' => 'PTY-100']);
        $this->assertRouteMatches('POST', '/api/v1/admin/parties/PTY-100/activate', $partyClass, 'activate', ['ref' => 'PTY-100']);
    }

    public function testTransporterAndMastersRoutes(): void
    {
        $masterClass = \App\Http\Controllers\Api\V1\Admin\MastersController::class;
        $catalogClass = \App\Http\Controllers\Api\V1\Admin\CatalogMastersController::class;

        $this->assertRouteMatches('GET', '/api/v1/admin/transporters/TRN-100', $masterClass, 'showTransporter', ['ref' => 'TRN-100']);
        $this->assertRouteMatches('PATCH', '/api/v1/admin/transporters/TRN-100', $masterClass, 'updateTransporter', ['ref' => 'TRN-100']);
        $this->assertRouteMatches('POST', '/api/v1/admin/transporters/TRN-100/status', $masterClass, 'transporterStatus', ['ref' => 'TRN-100']);

        // Generic master aliases
        $this->assertRouteMatches('GET', '/api/v1/admin/masters/lead-sources', $catalogClass, 'index', ['category' => 'lead-sources']);
        $this->assertRouteMatches('POST', '/api/v1/admin/masters/lead-sources', $catalogClass, 'create', ['category' => 'lead-sources']);
        $this->assertRouteMatches('GET', '/api/v1/admin/masters/lead-sources/MST-100', $catalogClass, 'show', ['category' => 'lead-sources', 'ref' => 'MST-100']);
        $this->assertRouteMatches('PATCH', '/api/v1/admin/masters/lead-sources/MST-100', $catalogClass, 'update', ['category' => 'lead-sources', 'ref' => 'MST-100']);
        $this->assertRouteMatches('POST', '/api/v1/admin/masters/lead-sources/MST-100/status', $catalogClass, 'status', ['category' => 'lead-sources', 'ref' => 'MST-100']);
    }
}
