<?php
namespace App\Repositories\Sql;

use App\Repositories\Contracts\OrderRepositoryInterface;
use PDO;
use Exception;

class OrderRepository implements OrderRepositoryInterface {
    private PDO $db;

    public function __construct(PDO $db) {
        $this->db = $db;
    }

    public function createOrder(array $orderData, array $orderItems): int {
        $this->db->beginTransaction();
        
        try {
            $stmt = $this->db->prepare("
                INSERT INTO orders (customer_id, user_id, order_date, status, total_amount, notes)
                VALUES (:customer_id, :user_id, :order_date, 'pending', :total_amount, :notes)
            ");
            $stmt->execute([
                'customer_id' => $orderData['customer_id'],
                'user_id' => $orderData['user_id'],
                'order_date' => $orderData['order_date'],
                'total_amount' => $orderData['total_amount'],
                'notes' => $orderData['notes'] ?? null
            ]);
            
            $orderId = (int)$this->db->lastInsertId();
            
            $itemStmt = $this->db->prepare("
                INSERT INTO order_items (order_id, product_id, quantity, unit_price, total_price)
                VALUES (:order_id, :product_id, :quantity, :unit_price, :total_price)
            ");
            
            foreach ($orderItems as $item) {
                $itemStmt->execute([
                    'order_id' => $orderId,
                    'product_id' => $item['product_id'],
                    'quantity' => $item['quantity'],
                    'unit_price' => $item['unit_price'],
                    'total_price' => $item['total_price']
                ]);
            }
            
            $this->db->commit();
            return $orderId;
        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    public function getOrderById(int $id): ?array {
        $stmt = $this->db->prepare("
            SELECT o.*, c.name as customer_name, u.name as rep_name 
            FROM orders o
            JOIN customers c ON o.customer_id = c.id
            JOIN users u ON o.user_id = u.id
            WHERE o.id = :id
        ");
        $stmt->execute(['id' => $id]);
        $order = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$order) return null;
        
        $itemStmt = $this->db->prepare("
            SELECT oi.*, p.name as product_name, p.sku_code as sku 
            FROM order_items oi
            JOIN products p ON oi.product_id = p.id
            WHERE oi.order_id = :order_id
        ");
        $itemStmt->execute(['order_id' => $id]);
        $order['items'] = $itemStmt->fetchAll(PDO::FETCH_ASSOC);
        
        return $order;
    }

    public function listOrders(array $filters, int $limit = 50, int $offset = 0): array {
        $sql = "
            SELECT o.*, c.name as customer_name, u.name as rep_name 
            FROM orders o
            JOIN customers c ON o.customer_id = c.id
            JOIN users u ON o.user_id = u.id
            WHERE 1=1
        ";
        $params = [];
        
        if (!empty($filters['user_id'])) {
            $sql .= " AND o.user_id = :user_id";
            $params['user_id'] = $filters['user_id'];
        }
        if (!empty($filters['status'])) {
            $sql .= " AND o.status = :status";
            $params['status'] = $filters['status'];
        }
        if (!empty($filters['customer_id'])) {
            $sql .= " AND o.customer_id = :customer_id";
            $params['customer_id'] = $filters['customer_id'];
        }
        
        $sql .= " ORDER BY o.created_at DESC LIMIT :limit OFFSET :offset";
        $stmt = $this->db->prepare($sql);
        
        foreach ($params as $k => $v) {
            $stmt->bindValue($k, $v);
        }
        $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue('offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function updateOrderStatus(int $id, string $status, ?string $notes = null): bool {
        $stmt = $this->db->prepare("UPDATE orders SET status = :status, notes = COALESCE(:notes, notes) WHERE id = :id");
        return $stmt->execute([
            'status' => $status,
            'notes' => $notes,
            'id' => $id
        ]);
    }

    public function getInventory(int $productId): ?array {
        $stmt = $this->db->prepare("SELECT * FROM inventory WHERE product_id = :pid");
        $stmt->execute(['pid' => $productId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function deductInventory(int $productId, int $quantity): bool {
        $stmt = $this->db->prepare("
            UPDATE inventory 
            SET quantity_available = quantity_available - :qty1 
            WHERE product_id = :pid AND quantity_available >= :qty2
        ");
        $stmt->execute(['qty1' => $quantity, 'qty2' => $quantity, 'pid' => $productId]);
        return $stmt->rowCount() > 0;
    }

    public function listInventory(int $limit = 50, int $offset = 0): array {
        $stmt = $this->db->prepare("
            SELECT i.*, p.name, p.sku_code as sku, p.price 
            FROM inventory i
            JOIN products p ON i.product_id = p.id
            ORDER BY p.name ASC
            LIMIT :limit OFFSET :offset
        ");
        $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue('offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
