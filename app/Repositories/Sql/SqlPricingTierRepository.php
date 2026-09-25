<?php
declare(strict_types=1);
namespace App\Repositories\Sql;

use App\Core\Database;
use App\Repositories\Contracts\PricingTierRepositoryInterface;

final class SqlPricingTierRepository implements PricingTierRepositoryInterface
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
            $where[] = 'tier_name LIKE ?';
            $params[] = '%' . $filters['search'] . '%';
        }

        $clause = implode(' AND ', $where);
        $offset = ($page - 1) * $perPage;

        $total = (int) $this->db->fetchColumn(
            "SELECT COUNT(*) FROM pricing_tiers WHERE $clause",
            $params
        );

        $rows = $this->db->fetchAll(
            "SELECT * FROM pricing_tiers WHERE $clause ORDER BY tier_name ASC LIMIT ? OFFSET ?",
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

    public function findByRef(string $franchiseRef, string $tierRef): ?array
    {
        return $this->db->fetchOne(
            "SELECT * FROM pricing_tiers WHERE franchise_ref = ? AND tier_ref = ? LIMIT 1",
            [$franchiseRef, $tierRef]
        );
    }

    public function findByName(string $franchiseRef, string $tierName): ?array
    {
        return $this->db->fetchOne(
            "SELECT * FROM pricing_tiers WHERE franchise_ref = ? AND tier_name = ? LIMIT 1",
            [$franchiseRef, $tierName]
        );
    }

    public function create(array $data): string
    {
        $this->db->insert('pricing_tiers', $data);
        return $data['tier_ref'];
    }

    public function update(string $franchiseRef, string $tierRef, array $data): bool
    {
        return $this->db->update(
            'pricing_tiers',
            $data,
            'franchise_ref = ? AND tier_ref = ?',
            [$franchiseRef, $tierRef]
        ) > 0;
    }
}
