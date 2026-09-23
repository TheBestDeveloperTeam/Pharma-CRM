<?php
namespace App\Controllers\Web;

class OrderController
{
    public function index()
    {
        ob_start();
        include __DIR__ . '/../../../views/pages/orders.php';
        $content = ob_get_clean();

        include __DIR__ . '/../../../views/layouts/master.php';
    }
}
