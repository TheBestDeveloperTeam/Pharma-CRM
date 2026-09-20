<?php
declare(strict_types=1);
namespace App\Http\Controllers\Web;

use App\Core\{Request, Response};

final class AuthController
{
    public function superLogin(Request $r): Response
    {
        return $this->renderLogin('super', 'theme-super', 'crm-super', 'Super Admin Login', false);
    }

    public function adminLogin(Request $r): Response
    {
        return $this->renderLogin('admin', 'theme-admin', 'crm-admin', 'Franchise Admin Login', true);
    }

    public function salesLogin(Request $r): Response
    {
        return $this->renderLogin('sales', 'theme-sales', 'crm-sales', 'Sales Representative Login', true);
    }

    public function portalLogin(Request $r): Response
    {
        return $this->renderLogin('portal', 'theme-portal', 'crm-portal', 'Distributor Partner Portal', true);
    }

    private function renderLogin(string $surface, string $theme, string $clientId, string $title, bool $needsFranchise): Response
    {
        ob_start();
        $frnRequired = $needsFranchise;
        require __DIR__ . '/../../Views/auth/login.php';
        $html = ob_get_clean();

        return Response::html(200, $html ?: '');
    }
}
