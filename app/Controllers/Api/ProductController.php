<?php
namespace App\Controllers\Api;

class ProductController
{
    public function index()
    {
        header('Content-Type: application/json');
        echo json_encode([
            'status' => 'success',
            'data' => [
                ['id' => 1, 'ref' => 'PRD-001', 'name' => 'Paracetamol 500mg', 'category_name' => 'Tablet', 'is_active' => true],
                ['id' => 2, 'ref' => 'PRD-002', 'name' => 'Amoxicillin 250mg', 'category_name' => 'Capsule', 'is_active' => true]
            ]
        ]);
    }

    public function create()
    {
        header('Content-Type: application/json');
        echo json_encode(['status' => 'success', 'message' => 'Product created']);
    }
}
