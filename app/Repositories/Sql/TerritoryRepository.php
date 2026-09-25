<?php

declare(strict_types=1);

namespace App\Repositories\Sql;

use App\Core\Database;
use App\Repositories\Contracts\TerritoryRepositoryInterface;

class TerritoryRepository implements TerritoryRepositoryInterface
{
    public function __construct(private readonly Database $db) {}

    public function findById(int $id): ?array
    {
        return $this->db->fetchOne("SELECT * FROM territories WHERE id = ?", [$id]);
    }

    public function paginate(array $filters, int $page, int $perPage): array
    {
        $where  = ['1=1'];
        $params = [];

        if (!empty($filters['status'])) {
            $where[]  = 'status = ?';
            $params[] = $filters['status'];
        }
        if (!empty($filters['type'])) {
            $where[]  = 'type = ?';
            $params[] = $filters['type'];
        }
        if (!empty($filters['search'])) {
            $where[]  = 'name LIKE ?';
            $params[] = '%' . $filters['search'] . '%';
        }

        $whereClause = implode(' AND ', $where);
        $offset      = ($page - 1) * $perPage;

        $total = (int) $this->db->fetchColumn(
            "SELECT COUNT(*) FROM territories WHERE $whereClause",
            $params
        );

        $rows = $this->db->fetchAll(
            "SELECT * FROM territories WHERE $whereClause ORDER BY created_at DESC LIMIT ? OFFSET ?",
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
        return $this->db->insert('territories', $data);
    }

    public function update(int $id, array $data): void
    {
        $this->db->update('territories', $data, ['id' => $id]);
    }

    public function getTree(): array
    {
        $all = $this->db->fetchAll("SELECT * FROM territories ORDER BY type, name");
        
        $map = [];
        foreach ($all as $item) {
            $item['children'] = [];
            $map[$item['id']] = $item;
        }

        $tree = [];
        foreach ($map as $id => &$node) {
            if ($node['parent_id'] !== null && isset($map[$node['parent_id']])) {
                $map[$node['parent_id']]['children'][] = &$node;
            } else {
                $tree[] = &$node;
            }
        }

        return $tree;
    }
}
