<?php
declare(strict_types=1);
namespace App\Repositories\Sql;

use App\Core\Database;
use App\Core\Pagination;
use App\Repositories\Contracts\ProductRepositoryInterface;

final class SqlProductRepository implements ProductRepositoryInterface
{
    public function __construct(private Database $db) {}

    public function listActive(string $franchiseRef): array
    {
        return $this->db->fetchAll(
            "SELECT p.*, c.category_name
             FROM products p
             LEFT JOIN product_categories c ON c.franchise_ref = p.franchise_ref AND c.category_ref = p.category_ref
             WHERE p.franchise_ref = ? AND p.status = 'ACTIVE'
             ORDER BY p.product_name ASC",
            [$franchiseRef]
        );
    }

    public function list(string $franchiseRef, array $filters, int $page, int $perPage): array
    {
        $where = ['p.franchise_ref = ?'];
        $params = [$franchiseRef];

        if (!empty($filters['status'])) {
            $where[] = 'p.status = ?';
            $params[] = $filters['status'];
        }
        if (!empty($filters['category_ref'])) {
            $where[] = 'p.category_ref = ?';
            $params[] = $filters['category_ref'];
        }
        if (!empty($filters['search'])) {
            $where[] = '(p.product_name LIKE ? OR p.sku LIKE ? OR p.composition LIKE ?)';
            $s = '%' . $filters['search'] . '%';
            $params[] = $s;
            $params[] = $s;
            $params[] = $s;
        }

        $clause = implode(' AND ', $where);
        $offset = ($page - 1) * $perPage;

        $total = (int) $this->db->fetchColumn(
            "SELECT COUNT(*) FROM products p WHERE $clause",
            $params
        );

        $rows = $this->db->fetchAll(
            "SELECT p.*, c.category_name
             FROM products p
             LEFT JOIN product_categories c ON c.franchise_ref = p.franchise_ref AND c.category_ref = p.category_ref
             WHERE $clause
             ORDER BY p.product_name ASC
             LIMIT ? OFFSET ?",
            array_merge($params, [$perPage, $offset])
        );

        return ['data' => $rows, 'meta' => Pagination::meta($page, $perPage, $total)];
    }

    public function findByRef(string $franchiseRef, string $productRef): ?array
    {
        return $this->db->fetchOne(
            "SELECT p.*, c.category_name
             FROM products p
             LEFT JOIN product_categories c ON c.franchise_ref = p.franchise_ref AND c.category_ref = p.category_ref
             WHERE p.franchise_ref = ? AND p.product_ref = ? LIMIT 1",
            [$franchiseRef, $productRef]
        );
    }

    public function findBySku(string $franchiseRef, string $sku): ?array
    {
        return $this->db->fetchOne(
            "SELECT * FROM products WHERE franchise_ref = ? AND sku = ? LIMIT 1",
            [$franchiseRef, strtoupper(trim($sku))]
        );
    }

    public function create(array $data): string
    {
        $this->db->insert('products', $data);
        return $data['product_ref'];
    }

    public function update(string $franchiseRef, string $productRef, array $data): bool
    {
        return $this->db->update(
            'products',
            $data,
            'franchise_ref = ? AND product_ref = ?',
            [$franchiseRef, $productRef]
        ) > 0;
    }

    public function setStatus(string $franchiseRef, string $productRef, string $status): bool
    {
        return $this->db->update(
            'products',
            ['status' => $status],
            'franchise_ref = ? AND product_ref = ?',
            [$franchiseRef, $productRef]
        ) > 0;
    }

    public function isReferencedInOrders(string $franchiseRef, string $productRef): bool
    {
        $count = (int) $this->db->fetchColumn(
            "SELECT COUNT(*) FROM order_items WHERE franchise_ref = ? AND product_ref = ?",
            [$franchiseRef, $productRef]
        );
        return $count > 0;
    }

    public function isReferenced(string $franchiseRef, string $productRef): bool
    {
        $checks = [
            'order_items' => 'franchise_ref = ? AND product_ref = ?',
            'scheme_rules' => 'franchise_ref = ? AND product_ref = ?',
            'product_prices' => 'franchise_ref = ? AND product_ref = ?',
        ];
        foreach ($checks as $table => $where) {
            if ((int)$this->db->fetchColumn("SELECT COUNT(*) FROM {$table} WHERE {$where}", [$franchiseRef, $productRef]) > 0) return true;
        }
        return false;
    }

    public function delete(string $franchiseRef, string $productRef): bool
    {
        $stmt = $this->db->pdo()->prepare('DELETE FROM products WHERE franchise_ref = ? AND product_ref = ?');
        $stmt->execute([$franchiseRef, $productRef]);
        return $stmt->rowCount() > 0;
    }
}
