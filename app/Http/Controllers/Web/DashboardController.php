<?php
declare(strict_types=1);
namespace App\Http\Controllers\Web;

use App\Core\{Request, Response};

final class DashboardController
{
    public function superDashboard(Request $r): Response
    {
        return $this->renderShell('super', 'theme-super', 'Platform Overview', 'pages/dashboard');
    }

    public function adminDashboard(Request $r): Response
    {
        return $this->renderShell('admin', 'theme-admin', 'Franchise Dashboard', 'pages/dashboard');
    }

    public function adminCategories(Request $r): Response
    {
        return $this->renderShell('admin', 'theme-admin', 'Product Categories', 'pages/categories');
    }

    public function adminTiers(Request $r): Response
    {
        return $this->renderShell('admin', 'theme-admin', 'Pricing Tiers', 'pages/tiers');
    }

    public function adminProducts(Request $r): Response
    {
        return $this->renderShell('admin', 'theme-admin', 'Products Catalogue', 'pages/products');
    }

    public function adminPrices(Request $r): Response
    {
        return $this->renderShell('admin', 'theme-admin', 'Pricing Rules', 'pages/prices');
    }

    public function adminSchemes(Request $r): Response
    {
        return $this->renderShell('admin', 'theme-admin', 'Promotional Schemes', 'pages/schemes');
    }

    public function adminLeads(Request $r): Response
    {
        return $this->renderShell('admin', 'theme-admin', 'Leads Management', 'pages/leads');
    }

    public function adminFollowUps(Request $r): Response
    {
        return $this->renderShell('admin', 'theme-admin', 'Follow-up Queue', 'pages/followups');
    }

    public function adminParties(Request $r): Response
    {
        return $this->renderShell('admin', 'theme-admin', 'Parties & Distributors', 'pages/parties');
    }

    public function adminTerritories(Request $r): Response
    {
        return $this->renderShell('admin', 'theme-admin', 'Territory Allocations', 'pages/territories');
    }

    public function adminOrders(Request $r): Response
    {
        return $this->renderShell('admin', 'theme-admin', 'Order Management', 'pages/orders');
    }

    public function adminInvoices(Request $r): Response
    {
        return $this->renderShell('admin', 'theme-admin', 'Invoices', 'pages/invoices');
    }

    public function adminInventory(Request $r): Response
    {
        return $this->renderShell('admin', 'theme-admin', 'Inventory Control', 'pages/inventory');
    }

    public function adminPayments(Request $r): Response
    {
        return $this->renderShell('admin', 'theme-admin', 'Payments & Receipts', 'pages/payments');
    }

    public function adminDispatches(Request $r): Response
    {
        return $this->renderShell('admin', 'theme-admin', 'Dispatches', 'pages/dispatches');
    }

    public function adminUsers(Request $r): Response
    {
        return $this->renderShell('admin', 'theme-admin', 'User Management', 'pages/users');
    }

    public function adminSettings(Request $r): Response
    {
        return $this->renderShell('admin', 'theme-admin', 'System Settings', 'pages/settings');
    }

    public function adminReports(Request $r): Response
    {
        return $this->renderShell('admin', 'theme-admin', 'Reports & Analytics', 'pages/reports');
    }

    public function adminNotifications(Request $r): Response
    {
        return $this->renderShell('admin', 'theme-admin', 'Notifications', 'pages/notifications');
    }

    public function salesDashboard(Request $r): Response
    {
        return $this->renderShell('sales', 'theme-sales', 'Sales Dashboard', 'pages/dashboard');
    }

    public function portalDashboard(Request $r): Response
    {
        return $this->renderShell('portal', 'theme-portal', 'Distributor Portal', 'pages/dashboard');
    }

    private function renderShell(string $surface, string $theme, string $title, string $viewName): Response
    {
        ob_start();
        $viewPath = __DIR__ . '/../../Views/' . $viewName . '.php';
        
        if (file_exists($viewPath)) {
            ob_start();
            require $viewPath;
            $content = ob_get_clean();
        } else {
            $content = '<div class="content"><div class="container-fluid">View not implemented: ' . htmlspecialchars($viewName) . '</div></div>';
        }
        
        require __DIR__ . '/../../Views/layouts/master.php';
        $html = ob_get_clean();

        return Response::html(200, $html ?: '');
    }
}
