<?php
namespace App\Controllers\Api;

class DashboardController
{
    public function stats()
    {
        header('Content-Type: application/json');
        echo json_encode([
            'status' => 'success',
            'data' => [
                'orders_count' => 125,
                'leads_count' => 45,
                'products_count' => 312
            ]
        ]);
    }
}
