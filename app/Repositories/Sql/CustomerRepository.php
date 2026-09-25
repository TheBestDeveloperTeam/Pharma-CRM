<?php

declare(strict_types=1);

namespace App\Repositories\Sql;

use App\Core\Database;
use App\Repositories\Contracts\CustomerRepositoryInterface;

class CustomerRepository implements CustomerRepositoryInterface
{
    public function __construct(private readonly Database $db) {}

    public function findById(int $id): ?array
    {
        return $this->db->fetchOne(
            "SELECT c.*, t.name as territory_name 
             FROM customers c 
             LEFT JOIN territories t ON t.id = c.territory_id 
             WHERE c.id = ?", 
            [$id]
        );
    }

    public function paginate(array $filters, int $page, int $perPage): array
    {
        $where  = ['1=1'];
        $params = [];

        if (!empty($filters['status'])) {
            $where[]  = 'c.status = ?';
            $params[] = $filters['status'];
        }
        if (!empty($filters['type'])) {
            $where[]  = 'c.type = ?';
            $params[] = $filters['type'];
        }
        if (!empty($filters['territory_id'])) {
            $where[]  = 'c.territory_id = ?';
            $params[] = $filters['territory_id'];
        }
        if (!empty($filters['search'])) {
            $where[]  = '(c.name LIKE ? OR c.email LIKE ? OR c.phone LIKE ?)';
            $s = '%' . $filters['search'] . '%';
            $params[] = $s;
            $params[] = $s;
            $params[] = $s;
        }

        $whereClause = implode(' AND ', $where);
        $offset      = ($page - 1) * $perPage;

        $total = (int) $this->db->fetchColumn(
            "SELECT COUNT(*) FROM customers c WHERE $whereClause",
            $params
        );

        $rows = $this->db->fetchAll(
            "SELECT c.*, t.name as territory_name 
             FROM customers c 
             LEFT JOIN territories t ON t.id = c.territory_id 
             WHERE $whereClause 
             ORDER BY c.created_at DESC LIMIT ? OFFSET ?",
            array_merge($params, [$perPage, $offset])
        );

        return [
            'data' => $rows,
            'meta' => [
                'page'     => $page,
                'per_page' => $perPage,
                'total'    => $total,
                'total_pages' => (int) ceil($total / $perPage),
            ],
        ];
    }

    public function create(array $data): int|string
    {
        return $this->db->insert('customers', $data);
    }

    public function update(int $id, array $data): void
    {
        $this->db->update('customers', $data, ['id' => $id]);
    }
}
