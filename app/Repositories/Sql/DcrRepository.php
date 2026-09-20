<?php
namespace App\Repositories\Sql;

use App\Repositories\Contracts\DcrRepositoryInterface;
use PDO;

class DcrRepository implements DcrRepositoryInterface {
    private PDO $db;

    public function __construct(PDO $db) {
        $this->db = $db;
    }

    public function createDcr(array $data): int {
        $stmt = $this->db->prepare("
            INSERT INTO dcrs (user_id, territory_id, dcr_date, status, created_by, updated_by)
            VALUES (:user_id, :territory_id, :dcr_date, 'draft', :created_by, :updated_by)
        ");
        $stmt->execute([
            'user_id' => $data['user_id'],
            'territory_id' => $data['territory_id'],
            'dcr_date' => $data['dcr_date'],
            'created_by' => $data['user_id'],
            'updated_by' => $data['user_id']
        ]);
        return (int)$this->db->lastInsertId();
    }

    public function getDcrById(int $id): ?array {
        $stmt = $this->db->prepare("SELECT * FROM dcrs WHERE id = :id");
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function updateDcrStatus(int $id, string $status, ?string $managerNotes = null): bool {
        $stmt = $this->db->prepare("
            UPDATE dcrs SET status = :status, manager_notes = :notes WHERE id = :id
        ");
        return $stmt->execute([
            'status' => $status,
            'notes' => $managerNotes,
            'id' => $id
        ]);
    }

    public function listDcrs(int $userId, string $date = null, string $status = null, int $limit = 50, int $offset = 0): array {
        $sql = "SELECT d.*, t.name as territory_name FROM dcrs d JOIN territories t ON d.territory_id = t.id WHERE d.user_id = :user_id";
        $params = ['user_id' => $userId];
        
        if ($date) {
            $sql .= " AND d.dcr_date = :date";
            $params['date'] = $date;
        }
        if ($status) {
            $sql .= " AND d.status = :status";
            $params['status'] = $status;
        }
        
        $sql .= " ORDER BY d.dcr_date DESC LIMIT :limit OFFSET :offset";
        $stmt = $this->db->prepare($sql);
        
        foreach ($params as $k => $v) {
            $stmt->bindValue($k, $v);
        }
        $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue('offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    public function getDcrByDateAndUser(string $date, int $userId): ?array {
        $stmt = $this->db->prepare("SELECT * FROM dcrs WHERE dcr_date = :date AND user_id = :user_id");
        $stmt->execute(['date' => $date, 'user_id' => $userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function addVisit(int $dcrId, array $visitData): int {
        $stmt = $this->db->prepare("
            INSERT INTO dcr_visits (dcr_id, customer_id, visit_time, notes)
            VALUES (:dcr_id, :customer_id, :visit_time, :notes)
        ");
        $stmt->execute([
            'dcr_id' => $dcrId,
            'customer_id' => $visitData['customer_id'],
            'visit_time' => $visitData['visit_time'] ?? null,
            'notes' => $visitData['notes'] ?? null
        ]);
        return (int)$this->db->lastInsertId();
    }

    public function getVisitById(int $id): ?array {
        $stmt = $this->db->prepare("SELECT * FROM dcr_visits WHERE id = :id");
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function listVisitsByDcr(int $dcrId): array {
        $stmt = $this->db->prepare("
            SELECT v.*, c.name, c.specialty 
            FROM dcr_visits v
            JOIN customers c ON v.customer_id = c.id
            WHERE v.dcr_id = :dcr_id
            ORDER BY v.visit_time ASC
        ");
        $stmt->execute(['dcr_id' => $dcrId]);
        $visits = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (empty($visits)) return [];
        
        // Fetch products for these visits
        $visitIds = array_column($visits, 'id');
        $placeholders = implode(',', array_fill(0, count($visitIds), '?'));
        $prodStmt = $this->db->prepare("
            SELECT dp.*, p.name, p.sku_code as sku 
            FROM dcr_products dp 
            JOIN products p ON dp.product_id = p.id
            WHERE dp.dcr_visit_id IN ($placeholders)
        ");
        $prodStmt->execute($visitIds);
        $products = $prodStmt->fetchAll(PDO::FETCH_ASSOC);
        
        $productsByVisit = [];
        foreach ($products as $p) {
            $productsByVisit[$p['dcr_visit_id']][] = $p;
        }
        
        foreach ($visits as &$v) {
            $v['products'] = $productsByVisit[$v['id']] ?? [];
        }
        
        return $visits;
    }

    public function addProductsToVisit(int $visitId, array $productsData): void {
        if (empty($productsData)) return;
        
        $values = [];
        $params = [];
        foreach ($productsData as $i => $pd) {
            $values[] = "(:visit_id_$i, :product_id_$i, :qty_$i)";
            $params["visit_id_$i"] = $visitId;
            $params["product_id_$i"] = $pd['product_id'];
            $params["qty_$i"] = $pd['quantity'] ?? 1;
        }
        
        $sql = "INSERT INTO dcr_products (dcr_visit_id, product_id, quantity) VALUES " . implode(', ', $values);
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
    }
}
