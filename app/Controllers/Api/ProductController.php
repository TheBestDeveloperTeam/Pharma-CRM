<?php
namespace App\Controllers\Api;

use App\Repositories\DB;
use App\Middleware\AuthMiddleware;

class ProductController
{
    public function index()
    {
        $payload = AuthMiddleware::authenticateApi();
        $tenantId = $payload['tenant'] ?? 0;

        $products = DB::query("SELECT * FROM products WHERE tenant_id = ? AND status = 'active'", [$tenantId])->fetchAll();

        header('Content-Type: application/json');
        echo json_encode([
            'status' => 'success',
            'data' => $products
        ]);
    }

    public function create()
    {
        $payload = AuthMiddleware::authenticateApi();
        AuthMiddleware::authorizeRole('admin');
        
        $tenantId = $payload['tenant'] ?? 0;
        
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (empty($input['sku']) || empty($input['name']) || empty($input['price'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Missing required fields']);
            return;
        }

        DB::query(
            "INSERT INTO products (tenant_id, sku, name, price, formula, status) VALUES (?, ?, ?, ?, ?, ?)",
            [$tenantId, $input['sku'], $input['name'], $input['price'], $input['formula'] ?? null, 'active']
        );
        
        $newId = DB::getInstance()->lastInsertId();
        
        DB::logAudit('CREATE', 'products', $newId, null, $input);

        http_response_code(201);
        header('Content-Type: application/json');
        echo json_encode(['status' => 'success', 'message' => 'Product created', 'id' => $newId]);
    }
}
