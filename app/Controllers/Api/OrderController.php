<?php
namespace App\Controllers\Api;

use App\Repositories\DB;
use App\Middleware\AuthMiddleware;
use PDO;
use Exception;

class OrderController
{
    public function __construct()
    {
        AuthMiddleware::authenticateApi();
    }

    public function index()
    {
        $tenantId = $_ENV['current_tenant_id'];
        $stmt = DB::query("SELECT * FROM orders WHERE tenant_id = ?", [$tenantId]);
        $orders = $stmt->fetchAll();

        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'data' => $orders]);
    }

    public function create()
    {
        $tenantId = $_ENV['current_tenant_id'];
        $data = json_decode(file_get_contents('php://input'), true);
        
        $doctorId = (int)($data['doctor_id'] ?? 0);
        $items = $data['items'] ?? [];

        if (!$doctorId || empty($items)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Missing doctor_id or items']);
            return;
        }

        try {
            DB::getInstance()->beginTransaction();

            $totalAmount = 0.0;
            foreach ($items as $item) {
                $totalAmount += ((float)$item['qty'] * (float)$item['unit_price']);
            }

            // Create Order
            DB::query("INSERT INTO orders (tenant_id, doctor_id, total_amount, status) VALUES (?, ?, ?, 'draft')", 
                [$tenantId, $doctorId, $totalAmount]);
            $orderId = DB::getInstance()->lastInsertId();

            // Create Items
            foreach ($items as $item) {
                $qty = (int)$item['qty'];
                $price = (float)$item['unit_price'];
                $subtotal = $qty * $price;
                $batchId = (int)$item['batch_id'];

                DB::query("INSERT INTO order_items (order_id, batch_id, qty, unit_price, subtotal) VALUES (?, ?, ?, ?, ?)", 
                    [$orderId, $batchId, $qty, $price, $subtotal]);
            }

            DB::getInstance()->commit();
            echo json_encode(['success' => true, 'message' => 'Order created', 'order_id' => $orderId]);
        } catch (Exception $e) {
            DB::getInstance()->rollBack();
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Failed to create order']);
        }
    }
}
