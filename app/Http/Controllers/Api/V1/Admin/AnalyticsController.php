<?php
declare(strict_types=1);
namespace App\Http\Controllers\Api\V1\Admin;

use App\Core\{Request, Response, TenantContext};
use App\Domain\Authorization\AuthorizationService;
use App\Domain\Reports\ScopedAnalyticsService;

final class AnalyticsController
{
    public function __construct(
        private ScopedAnalyticsService $service,
        private AuthorizationService $auth,
    ) {}

    private function ctx(): TenantContext
    {
        return TenantContext::get();
    }

    public function dashboard(Request $r): Response
    {
        $c = $this->ctx();
        $this->auth->requirePermission($c, 'dashboard', 'view');
        return Response::json(200, $this->service->dashboard($c));
    }

    // Individual Dashboard Widgets (DSH-001 to DSH-011)
    public function leadStats(Request $r): Response
    {
        $c = $this->ctx();
        $this->auth->requirePermission($c, 'dashboard', 'view');
        $dash = $this->service->dashboard($c);
        $leads = $dash['leads'] ?? [];
        return Response::json(200, [
            'newToday'          => $leads['newToday'] ?? 0,
            'unassignedCount'   => $leads['unassignedCount'] ?? 0,
            'slaBreachedCount'  => $leads['slaBreachedCount'] ?? 0,
            'slaThresholdHours' => $leads['slaThresholdHours'] ?? 4,
        ]);
    }

    public function followUpStats(Request $r): Response
    {
        $c = $this->ctx();
        $this->auth->requirePermission($c, 'dashboard', 'view');
        $dash = $this->service->dashboard($c);
        return Response::json(200, $dash['follow_ups'] ?? []);
    }

    public function slaBreachTrend(Request $r): Response
    {
        $c = $this->ctx();
        $this->auth->requirePermission($c, 'dashboard', 'view');
        $dash = $this->service->dashboard($c);
        return Response::json(200, $dash['leads']['slaTrend'] ?? []);
    }

    public function leadConversionStage(Request $r): Response
    {
        $c = $this->ctx();
        $this->auth->requirePermission($c, 'dashboard', 'view');
        $dash = $this->service->dashboard($c);
        return Response::json(200, $dash['leads']['conversionSeries'] ?? []);
    }

    public function orderStats(Request $r): Response
    {
        $c = $this->ctx();
        $this->auth->requirePermission($c, 'dashboard', 'view');
        $dash = $this->service->dashboard($c);
        $orders = $dash['orders'] ?? [];
        return Response::json(200, [
            'activeCount'          => $orders['activeCount'] ?? 0,
            'cancelledCount'       => $orders['cancelledCount'] ?? 0,
            'dispatchPendingCount' => $orders['dispatchPendingCount'] ?? 0,
        ]);
    }

    public function territoryViolations(Request $r): Response
    {
        $c = $this->ctx();
        $this->auth->requirePermission($c, 'dashboard', 'view');
        $dash = $this->service->dashboard($c);
        return Response::json(200, [
            'territoryViolationCount' => $dash['orders']['territoryViolationCount'] ?? 0,
        ]);
    }

    public function ordersSalesTrend(Request $r): Response
    {
        $c = $this->ctx();
        $this->auth->requirePermission($c, 'dashboard', 'view');
        $dash = $this->service->dashboard($c);
        return Response::json(200, $dash['orders']['salesTrend'] ?? []);
    }

    public function outstandingSummary(Request $r): Response
    {
        $c = $this->ctx();
        $this->auth->requirePermission($c, 'dashboard', 'view');
        $dash = $this->service->dashboard($c);
        return Response::json(200, [
            'total' => $dash['outstanding']['total'] ?? 0.0,
        ]);
    }

    public function outstandingAgeing(Request $r): Response
    {
        $c = $this->ctx();
        $this->auth->requirePermission($c, 'dashboard', 'view');
        $dash = $this->service->dashboard($c);
        return Response::json(200, $dash['outstanding']['buckets'] ?? []);
    }

    public function nearExpiryValue(Request $r): Response
    {
        $c = $this->ctx();
        $this->auth->requirePermission($c, 'dashboard', 'view');
        $dash = $this->service->dashboard($c);
        return Response::json(200, $dash['near_expiry'] ?? []);
    }

    public function salesTeamProductivity(Request $r): Response
    {
        $c = $this->ctx();
        $this->auth->requirePermission($c, 'dashboard', 'view');
        $dash = $this->service->dashboard($c);
        return Response::json(200, $dash['sales_team'] ?? []);
    }

    // Reports Engine (RPT-001 to RPT-016)
    public function report(Request $r, ?string $key = null): Response
    {
        $c = $this->ctx();
        $key = $key ?? (string)($r->param('key') ?: $r->param('type'));
        $module = [
            'lead-source'             => 'leads',
            'response-time'           => 'leads',
            'conversion'              => 'leads',
            'sales-team-productivity' => 'internalUsers',
            'territory-sales'         => 'orders',
            'party-sales'             => 'orders',
            'product-sales'           => 'orders',
            'scheme-utilization'      => 'schemes',
            'order-status'            => 'orders',
            'dispatch-pending'        => 'dispatch',
            'batch-inventory'         => 'inventory',
            'near-expiry'             => 'nearExpiry',
            'territory-violations'    => 'territory',
            'webhook-failures'        => 'webhooks',
            'whatsapp-delivery'       => 'notifications',
            'payment-outstanding'     => 'payments'
        ][$key] ?? 'reports';

        $this->auth->requirePermission($c, $module, 'view');
        return Response::json(200, $this->service->report($c, $key, $r->query));
    }

    // Dedicated report route methods
    public function leadSource(Request $r): Response { return $this->report($r, 'lead-source'); }
    public function responseTime(Request $r): Response { return $this->report($r, 'response-time'); }
    public function conversion(Request $r): Response { return $this->report($r, 'conversion'); }
    public function salesTeamProductivityReport(Request $r): Response { return $this->report($r, 'sales-team-productivity'); }
    public function territorySales(Request $r): Response { return $this->report($r, 'territory-sales'); }
    public function partySales(Request $r): Response { return $this->report($r, 'party-sales'); }
    public function productSales(Request $r): Response { return $this->report($r, 'product-sales'); }
    public function schemeUtilization(Request $r): Response { return $this->report($r, 'scheme-utilization'); }
    public function orderStatus(Request $r): Response { return $this->report($r, 'order-status'); }
    public function dispatchPending(Request $r): Response { return $this->report($r, 'dispatch-pending'); }
    public function paymentOutstanding(Request $r): Response { return $this->report($r, 'payment-outstanding'); }
    public function batchInventory(Request $r): Response { return $this->report($r, 'batch-inventory'); }
    public function nearExpiryReport(Request $r): Response { return $this->report($r, 'near-expiry'); }
    public function territoryViolationsReport(Request $r): Response { return $this->report($r, 'territory-violations'); }
    public function webhookFailures(Request $r): Response { return $this->report($r, 'webhook-failures'); }
    public function whatsappDelivery(Request $r): Response { return $this->report($r, 'whatsapp-delivery'); }
}
