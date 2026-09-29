<?php
declare(strict_types=1);

namespace Tests\Unit\Http;

use App\Core\Request;
use App\Core\Router;
use PHPUnit\Framework\TestCase;

final class P2RoutesTest extends TestCase
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

    public function testPortalRoutes(): void
    {
        $portalClass = \App\Http\Controllers\Api\V1\Portal\PortalController::class;

        $this->assertRouteMatches('GET', '/api/v1/portal/dashboard', $portalClass, 'dashboard');
        $this->assertRouteMatches('GET', '/api/v1/portal/orders/ORD-001/timeline', $portalClass, 'orderTimeline', ['ref' => 'ORD-001']);
        $this->assertRouteMatches('GET', '/api/v1/portal/invoices/INV-001', $portalClass, 'showInvoice', ['ref' => 'INV-001']);
        $this->assertRouteMatches('GET', '/api/v1/portal/invoices/INV-001/download', $portalClass, 'downloadInvoice', ['ref' => 'INV-001']);
        $this->assertRouteMatches('GET', '/api/v1/portal/dispatches/DSP-001', $portalClass, 'showDispatch', ['ref' => 'DSP-001']);
        $this->assertRouteMatches('GET', '/api/v1/portal/payments', $portalClass, 'payments');
        $this->assertRouteMatches('GET', '/api/v1/portal/outstanding/ageing', $portalClass, 'outstandingAgeing');
        $this->assertRouteMatches('GET', '/api/v1/portal/schemes', $portalClass, 'schemes');
        $this->assertRouteMatches('GET', '/api/v1/portal/support', $portalClass, 'support');
        $this->assertRouteMatches('GET', '/api/v1/portal/notifications', $portalClass, 'notifications');
        $this->assertRouteMatches('GET', '/api/v1/portal/shipping-addresses', $portalClass, 'listShippingAddresses');
        $this->assertRouteMatches('POST', '/api/v1/portal/shipping-addresses', $portalClass, 'addShippingAddress');
        $this->assertRouteMatches('PATCH', '/api/v1/portal/shipping-addresses/ADDR-001', $portalClass, 'updateShippingAddress', ['ref' => 'ADDR-001']);
        $this->assertRouteMatches('DELETE', '/api/v1/portal/shipping-addresses/ADDR-001', $portalClass, 'deleteShippingAddress', ['ref' => 'ADDR-001']);
        $this->assertRouteMatches('GET', '/api/v1/portal/team', $portalClass, 'listTeam');
        $this->assertRouteMatches('POST', '/api/v1/portal/team', $portalClass, 'createTeam');
        $this->assertRouteMatches('GET', '/api/v1/portal/team/USR-001', $portalClass, 'showTeam', ['ref' => 'USR-001']);
        $this->assertRouteMatches('PATCH', '/api/v1/portal/team/USR-001', $portalClass, 'updateTeam', ['ref' => 'USR-001']);
        $this->assertRouteMatches('POST', '/api/v1/portal/team/USR-001/activate', $portalClass, 'activateTeam', ['ref' => 'USR-001']);
        $this->assertRouteMatches('POST', '/api/v1/portal/team/USR-001/deactivate', $portalClass, 'deactivateTeam', ['ref' => 'USR-001']);
        $this->assertRouteMatches('POST', '/api/v1/portal/team/USR-001/assign-beat', $portalClass, 'assignBeat', ['ref' => 'USR-001']);
    }

    public function testDcrFieldOpsRoutes(): void
    {
        $dcrClass = \App\Http\Controllers\Api\DCR\DcrController::class;

        // DCR status lifecycle & visits
        $this->assertRouteMatches('POST', '/api/v1/portal/dcrs/DCR-001/submit', $dcrClass, 'submit', ['ref' => 'DCR-001']);
        $this->assertRouteMatches('POST', '/api/v1/portal/dcrs/DCR-001/approve', $dcrClass, 'approve', ['ref' => 'DCR-001']);
        $this->assertRouteMatches('POST', '/api/v1/portal/dcrs/DCR-001/reject', $dcrClass, 'reject', ['ref' => 'DCR-001']);
        $this->assertRouteMatches('POST', '/api/v1/portal/dcrs/DCR-001/reopen', $dcrClass, 'reopen', ['ref' => 'DCR-001']);
        $this->assertRouteMatches('GET', '/api/v1/portal/dcrs/DCR-001/visits', $dcrClass, 'visits', ['ref' => 'DCR-001']);
        $this->assertRouteMatches('POST', '/api/v1/portal/dcrs/DCR-001/visits', $dcrClass, 'addVisit', ['ref' => 'DCR-001']);
        $this->assertRouteMatches('PATCH', '/api/v1/portal/dcrs/DCR-001/visits/VIS-001', $dcrClass, 'updateVisit', ['ref' => 'DCR-001', 'visit_ref' => 'VIS-001']);
        $this->assertRouteMatches('DELETE', '/api/v1/portal/dcrs/DCR-001/visits/VIS-001', $dcrClass, 'deleteVisit', ['ref' => 'DCR-001', 'visit_ref' => 'VIS-001']);

        // Field Customers
        $this->assertRouteMatches('GET', '/api/v1/admin/field-customers', $dcrClass, 'listFieldCustomers');
        $this->assertRouteMatches('POST', '/api/v1/admin/field-customers', $dcrClass, 'createFieldCustomer');
        $this->assertRouteMatches('GET', '/api/v1/admin/field-customers/CUS-001', $dcrClass, 'showFieldCustomer', ['ref' => 'CUS-001']);
        $this->assertRouteMatches('PATCH', '/api/v1/admin/field-customers/CUS-001', $dcrClass, 'updateFieldCustomer', ['ref' => 'CUS-001']);

        // Beats
        $this->assertRouteMatches('GET', '/api/v1/admin/beats', $dcrClass, 'listBeats');
        $this->assertRouteMatches('POST', '/api/v1/admin/beats', $dcrClass, 'createBeat');
        $this->assertRouteMatches('PATCH', '/api/v1/admin/beats/BEA-001', $dcrClass, 'updateBeat', ['ref' => 'BEA-001']);

        // Tour Plans
        $this->assertRouteMatches('GET', '/api/v1/admin/tour-plans/TP-001/actual-comparison', $dcrClass, 'tourPlanComparison', ['ref' => 'TP-001']);
        $this->assertRouteMatches('GET', '/api/v1/admin/tour-plans', $dcrClass, 'listTourPlans');
        $this->assertRouteMatches('POST', '/api/v1/admin/tour-plans', $dcrClass, 'createTourPlan');
        $this->assertRouteMatches('PATCH', '/api/v1/admin/tour-plans/TP-001', $dcrClass, 'updateTourPlan', ['ref' => 'TP-001']);

        // POB & Reports
        $this->assertRouteMatches('GET', '/api/v1/admin/pob', $dcrClass, 'listPob');
        $this->assertRouteMatches('POST', '/api/v1/admin/pob/convert-to-order', $dcrClass, 'convertToOrder');
        $this->assertRouteMatches('GET', '/api/v1/admin/dcr-reports/daily-summary', $dcrClass, 'dailySummary');
        $this->assertRouteMatches('GET', '/api/v1/admin/dcr-reports/monthly-summary', $dcrClass, 'monthlySummary');
        $this->assertRouteMatches('GET', '/api/v1/admin/dcr-reports/coverage', $dcrClass, 'coverageReport');
        $this->assertRouteMatches('GET', '/api/v1/admin/dcr-reports/product-promotion', $dcrClass, 'productPromotionReport');
        $this->assertRouteMatches('GET', '/api/v1/admin/dcr-reports/missed-dcr', $dcrClass, 'missedDcrReport');
        $this->assertRouteMatches('GET', '/api/v1/admin/dcr-reports/sample-gift', $dcrClass, 'sampleGiftReport');
        $this->assertRouteMatches('GET', '/api/v1/admin/dcr-reports/expense', $dcrClass, 'expenseReport');
        $this->assertRouteMatches('GET', '/api/v1/admin/dcr-reports/pob-summary', $dcrClass, 'pobSummaryReport');
    }

    public function testDashboardAndAnalyticsReportsRoutes(): void
    {
        $analyticsClass = \App\Http\Controllers\Api\V1\Admin\AnalyticsController::class;

        // Dashboard widgets
        $this->assertRouteMatches('GET', '/api/v1/admin/dashboard/lead-stats', $analyticsClass, 'leadStats');
        $this->assertRouteMatches('GET', '/api/v1/admin/dashboard/follow-up-stats', $analyticsClass, 'followUpStats');
        $this->assertRouteMatches('GET', '/api/v1/admin/dashboard/sla-breach-trend', $analyticsClass, 'slaBreachTrend');
        $this->assertRouteMatches('GET', '/api/v1/admin/dashboard/lead-conversion-stage', $analyticsClass, 'leadConversionStage');
        $this->assertRouteMatches('GET', '/api/v1/admin/dashboard/order-stats', $analyticsClass, 'orderStats');
        $this->assertRouteMatches('GET', '/api/v1/admin/dashboard/territory-violations', $analyticsClass, 'territoryViolations');
        $this->assertRouteMatches('GET', '/api/v1/admin/dashboard/orders-sales-trend', $analyticsClass, 'ordersSalesTrend');
        $this->assertRouteMatches('GET', '/api/v1/admin/dashboard/outstanding-summary', $analyticsClass, 'outstandingSummary');
        $this->assertRouteMatches('GET', '/api/v1/admin/dashboard/outstanding-ageing', $analyticsClass, 'outstandingAgeing');
        $this->assertRouteMatches('GET', '/api/v1/admin/dashboard/near-expiry-value', $analyticsClass, 'nearExpiryValue');
        $this->assertRouteMatches('GET', '/api/v1/admin/dashboard/sales-team-productivity', $analyticsClass, 'salesTeamProductivity');
        $this->assertRouteMatches('GET', '/api/v1/admin/dashboard', $analyticsClass, 'dashboard');

        // Dedicated reports
        $this->assertRouteMatches('GET', '/api/v1/admin/reports/lead-source', $analyticsClass, 'leadSource');
        $this->assertRouteMatches('GET', '/api/v1/admin/reports/response-time', $analyticsClass, 'responseTime');
        $this->assertRouteMatches('GET', '/api/v1/admin/reports/conversion', $analyticsClass, 'conversion');
        $this->assertRouteMatches('GET', '/api/v1/admin/reports/sales-team-productivity', $analyticsClass, 'salesTeamProductivityReport');
        $this->assertRouteMatches('GET', '/api/v1/admin/reports/territory-sales', $analyticsClass, 'territorySales');
        $this->assertRouteMatches('GET', '/api/v1/admin/reports/party-sales', $analyticsClass, 'partySales');
        $this->assertRouteMatches('GET', '/api/v1/admin/reports/product-sales', $analyticsClass, 'productSales');
        $this->assertRouteMatches('GET', '/api/v1/admin/reports/scheme-utilization', $analyticsClass, 'schemeUtilization');
        $this->assertRouteMatches('GET', '/api/v1/admin/reports/order-status', $analyticsClass, 'orderStatus');
        $this->assertRouteMatches('GET', '/api/v1/admin/reports/dispatch-pending', $analyticsClass, 'dispatchPending');
        $this->assertRouteMatches('GET', '/api/v1/admin/reports/payment-outstanding', $analyticsClass, 'paymentOutstanding');
        $this->assertRouteMatches('GET', '/api/v1/admin/reports/batch-inventory', $analyticsClass, 'batchInventory');
        $this->assertRouteMatches('GET', '/api/v1/admin/reports/near-expiry', $analyticsClass, 'nearExpiryReport');
        $this->assertRouteMatches('GET', '/api/v1/admin/reports/territory-violations', $analyticsClass, 'territoryViolationsReport');
        $this->assertRouteMatches('GET', '/api/v1/admin/reports/webhook-failures', $analyticsClass, 'webhookFailures');
        $this->assertRouteMatches('GET', '/api/v1/admin/reports/whatsapp-delivery', $analyticsClass, 'whatsappDelivery');
    }

    public function testSettingsRoutes(): void
    {
        $settingsClass = \App\Http\Controllers\Api\V1\Admin\SettingsController::class;

        $this->assertRouteMatches('GET', '/api/v1/admin/settings/near-expiry-thresholds', $settingsClass, 'getNearExpiryThresholds');
        $this->assertRouteMatches('PATCH', '/api/v1/admin/settings/near-expiry-thresholds', $settingsClass, 'updateNearExpiryThresholds');
        $this->assertRouteMatches('GET', '/api/v1/admin/settings/sla', $settingsClass, 'getSla');
        $this->assertRouteMatches('PATCH', '/api/v1/admin/settings/sla', $settingsClass, 'updateSla');
        $this->assertRouteMatches('GET', '/api/v1/admin/settings/territory-policy', $settingsClass, 'getTerritoryPolicy');
        $this->assertRouteMatches('PATCH', '/api/v1/admin/settings/territory-policy', $settingsClass, 'updateTerritoryPolicy');
        $this->assertRouteMatches('GET', '/api/v1/admin/settings/credit-policy', $settingsClass, 'getCreditPolicy');
        $this->assertRouteMatches('PATCH', '/api/v1/admin/settings/credit-policy', $settingsClass, 'updateCreditPolicy');
        $this->assertRouteMatches('GET', '/api/v1/admin/settings/dcr-config', $settingsClass, 'getDcrConfig');
        $this->assertRouteMatches('PATCH', '/api/v1/admin/settings/dcr-config', $settingsClass, 'updateDcrConfig');
        $this->assertRouteMatches('GET', '/api/v1/admin/settings/invite-config', $settingsClass, 'getInviteConfig');
        $this->assertRouteMatches('PATCH', '/api/v1/admin/settings/invite-config', $settingsClass, 'updateInviteConfig');
        $this->assertRouteMatches('GET', '/api/v1/admin/settings/scheme-stacking', $settingsClass, 'getSchemeStacking');
        $this->assertRouteMatches('PATCH', '/api/v1/admin/settings/scheme-stacking', $settingsClass, 'updateSchemeStacking');
        $this->assertRouteMatches('GET', '/api/v1/admin/settings/min-shelf-life', $settingsClass, 'getMinShelfLife');
        $this->assertRouteMatches('PATCH', '/api/v1/admin/settings/min-shelf-life', $settingsClass, 'updateMinShelfLife');
    }
}
