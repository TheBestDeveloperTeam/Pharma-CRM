<?php
declare(strict_types=1);
namespace App\Repositories\Sql;

use App\Core\Database;
use App\Repositories\Contracts\ProductCategoryRepositoryInterface;

final class SqlProductCategoryRepository implements ProductCategoryRepositoryInterface
{
    public function __construct(private Database $db) {}

    public function list(string $franchiseRef, array $filters, int $page, int $perPage): array
    {
        $where = ['franchise_ref = ?'];
        $params = [$franchiseRef];

        if (!empty($filters['status'])) {
            $where[] = 'status = ?';
            $params[] = $filters['status'];
        }
        if (!empty($filters['search'])) {
            $where[] = 'category_name LIKE ?';
            $params[] = '%' . $filters['search'] . '%';
        }

        $clause = implode(' AND ', $where);
        $offset = ($page - 1) * $perPage;

        $total = (int) $this->db->fetchColumn(
            "SELECT COUNT(*) FROM product_categories WHERE $clause",
            $params
        );

        $rows = $this->db->fetchAll(
            "SELECT * FROM product_categories WHERE $clause ORDER BY category_name ASC LIMIT ? OFFSET ?",
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

    public function findByRef(string $franchiseRef, string $categoryRef): ?array
    {
        return $this->db->fetchOne(
            "SELECT * FROM product_categories WHERE franchise_ref = ? AND category_ref = ? LIMIT 1",
            [$franchiseRef, $categoryRef]
        );
    }

    public function findByName(string $franchiseRef, string $categoryName): ?array
    {
        return $this->db->fetchOne(
            "SELECT * FROM product_categories WHERE franchise_ref = ? AND category_name = ? LIMIT 1",
            [$franchiseRef, $categoryName]
        );
    }

    public function create(array $data): string
    {
        $this->db->insert('product_categories', $data);
        return $data['category_ref'];
    }

    public function update(string $franchiseRef, string $categoryRef, array $data): bool
    {
        return $this->db->update(
            'product_categories',
            $data,
            'franchise_ref = ? AND category_ref = ?',
            [$franchiseRef, $categoryRef]
        ) > 0;
    }
}
