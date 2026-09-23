<?php
namespace App\Controllers\Web;

class DashboardController
{
    public function index()
    {
        ob_start();
        include __DIR__ . '/../../../views/pages/dashboard.php';
        $content = ob_get_clean();

        include __DIR__ . '/../../../views/layouts/master.php';
    }
}
