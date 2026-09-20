<?php

declare(strict_types=1);

namespace App\Repositories\Sql;

use App\Core\Database;
use App\Repositories\Contracts\ProductRepositoryInterface;

class ProductRepository implements ProductRepositoryInterface
{
    public function __construct(private readonly Database $db) {}

    public function findById(int $id): ?array
    {
        return $this->db->fetchOne("SELECT * FROM products WHERE id = ?", [$id]);
    }

    public function findBySku(string $sku): ?array
    {
        return $this->db->fetchOne("SELECT * FROM products WHERE sku_code = ?", [$sku]);
    }

    public function paginate(array $filters, int $page, int $perPage): array
    {
        $where  = ['1=1'];
        $params = [];

        if (!empty($filters['status'])) {
            $where[]  = 'status = ?';
            $params[] = $filters['status'];
        }
        if (!empty($filters['category'])) {
            $where[]  = 'category = ?';
            $params[] = $filters['category'];
        }
        if (!empty($filters['search'])) {
            $where[]  = '(name LIKE ? OR sku_code LIKE ?)';
            $params[] = '%' . $filters['search'] . '%';
            $params[] = '%' . $filters['search'] . '%';
        }

        $whereClause = implode(' AND ', $where);
        $offset      = ($page - 1) * $perPage;

        $total = (int) $this->db->fetchColumn(
            "SELECT COUNT(*) FROM products WHERE $whereClause",
            $params
        );

        $rows = $this->db->fetchAll(
            "SELECT * FROM products WHERE $whereClause ORDER BY created_at DESC LIMIT ? OFFSET ?",
            array_merge($params, [$perPage, $offset])
        );

        return [
            'data' => $rows,
            'meta' => [
                'page'     => $page,
                'per_page' => $perPage,
                'total'    => $total,
                'pages'    => (int) ceil($total / $perPage),
            ],
        ];
    }

    public function create(array $data): int|string
    {
        return $this->db->insert('products', $data);
    }

    public function update(int $id, array $data): void
    {
        $this->db->update('products', $data, ['id' => $id]);
    }
}
