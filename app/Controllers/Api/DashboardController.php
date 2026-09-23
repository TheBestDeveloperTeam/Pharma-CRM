<?php
namespace App\Controllers\Api;

use App\Repositories\DB;
use App\Middleware\AuthMiddleware;

class DashboardController
{
    public function stats()
    {
        $payload = AuthMiddleware::authenticateApi();
        $tenantId = $payload['tenant'] ?? 0;

        // Fetch counts for dashboard metrics
        $ordersCount = DB::query("SELECT COUNT(*) as count FROM orders WHERE tenant_id = ?", [$tenantId])->fetchColumn();
        $leadsCount = DB::query("SELECT COUNT(*) as count FROM leads WHERE tenant_id = ?", [$tenantId])->fetchColumn();
        $productsCount = DB::query("SELECT COUNT(*) as count FROM products WHERE tenant_id = ? AND status = 'active'", [$tenantId])->fetchColumn();

        header('Content-Type: application/json');
        echo json_encode([
            'status' => 'success',
            'data' => [
                'orders_count' => (int)$ordersCount,
                'leads_count' => (int)$leadsCount,
                'products_count' => (int)$productsCount
            ]
        ]);
    }
}
