<?php
namespace App\Controllers\Api;

class LeadController
{
    public function index()
    {
        header('Content-Type: application/json');
        echo json_encode([
            'status' => 'success',
            'data' => [
                ['id' => 1, 'ref' => 'LD-1001', 'name' => 'Dr. Smith', 'phone' => '123-456-7890', 'status' => 'New'],
                ['id' => 2, 'ref' => 'LD-1002', 'name' => 'City Hospital', 'phone' => '098-765-4321', 'status' => 'Contacted']
            ]
        ]);
    }

    public function create()
    {
        header('Content-Type: application/json');
        echo json_encode(['status' => 'success', 'message' => 'Lead created']);
    }
}
