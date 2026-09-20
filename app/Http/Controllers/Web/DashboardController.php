<?php
declare(strict_types=1);
namespace App\Http\Controllers\Web;

use App\Core\{Request, Response};

final class DashboardController
{
    public function superDashboard(Request $r): Response
    {
        return $this->renderShell('super', 'theme-super', 'Platform Overview');
    }

    public function adminDashboard(Request $r): Response
    {
        return $this->renderShell('admin', 'theme-admin', 'Franchise Dashboard');
    }

    public function adminCategories(Request $r): Response
    {
        return $this->renderShell('admin', 'theme-admin', 'Product Categories');
    }

    public function adminTiers(Request $r): Response
    {
        return $this->renderShell('admin', 'theme-admin', 'Pricing Tiers');
    }

    public function adminProducts(Request $r): Response
    {
        return $this->renderShell('admin', 'theme-admin', 'Products Catalogue');
    }

    public function adminPrices(Request $r): Response
    {
        return $this->renderShell('admin', 'theme-admin', 'Pricing Rules');
    }

    public function adminSchemes(Request $r): Response
    {
        return $this->renderShell('admin', 'theme-admin', 'Promotional Schemes');
    }

    public function adminLeads(Request $r): Response
    {
        return $this->renderShell('admin', 'theme-admin', 'Leads Management');
    }

    public function adminFollowUps(Request $r): Response
    {
        return $this->renderShell('admin', 'theme-admin', 'Follow-up Queue');
    }

    public function adminParties(Request $r): Response
    {
        return $this->renderShell('admin', 'theme-admin', 'Parties & Distributors');
    }

    public function adminTerritories(Request $r): Response
    {
        return $this->renderShell('admin', 'theme-admin', 'Territory Allocations');
    }

    public function salesDashboard(Request $r): Response
    {
        return $this->renderShell('sales', 'theme-sales', 'Sales Dashboard');
    }

    public function portalDashboard(Request $r): Response
    {
        return $this->renderShell('portal', 'theme-portal', 'Distributor Portal');
    }

    private function renderShell(string $surface, string $theme, string $title): Response
    {
        ob_start();
        require __DIR__ . '/../../Views/layouts/shell.php';
        $html = ob_get_clean();

        return Response::html(200, $html ?: '');
    }
}
