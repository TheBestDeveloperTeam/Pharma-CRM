<?php
declare(strict_types=1);
namespace App\Repositories\Sql;

use App\Core\Database;
use App\Repositories\Contracts\SchemeRepositoryInterface;

final class SqlSchemeRepository implements SchemeRepositoryInterface
{
    public function __construct(private Database $db) {}

    public function list(string $franchiseRef, array $filters, int $page, int $perPage): array
    {
        $where = ['s.franchise_ref = ?'];
        $params = [$franchiseRef];

        if (!empty($filters['status'])) {
            $where[] = 's.status = ?';
            $params[] = $filters['status'];
        }
        if (!empty($filters['tier_ref'])) {
            $where[] = 's.tier_ref = ?';
            $params[] = $filters['tier_ref'];
        }

        $clause = implode(' AND ', $where);
        $offset = ($page - 1) * $perPage;

        $total = (int) $this->db->fetchColumn(
            "SELECT COUNT(*) FROM schemes s WHERE $clause",
            $params
        );

        $rows = $this->db->fetchAll(
            "SELECT s.*, t.tier_name
             FROM schemes s
             LEFT JOIN pricing_tiers t ON t.franchise_ref = s.franchise_ref AND t.tier_ref = s.tier_ref
             WHERE $clause
             ORDER BY s.priority ASC, s.start_date DESC
             LIMIT ? OFFSET ?",
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

    public function findByRef(string $franchiseRef, string $schemeRef): ?array
    {
        return $this->db->fetchOne(
            "SELECT * FROM schemes WHERE franchise_ref = ? AND scheme_ref = ? LIMIT 1",
            [$franchiseRef, $schemeRef]
        );
    }

    public function findRules(string $franchiseRef, string $schemeRef): array
    {
        return $this->db->fetchAll(
            "SELECT sr.*, p.product_name, p.sku
             FROM scheme_rules sr
             JOIN products p ON p.franchise_ref = sr.franchise_ref AND p.product_ref = sr.product_ref
             WHERE sr.franchise_ref = ? AND sr.scheme_ref = ?
             ORDER BY sr.min_qty ASC",
            [$franchiseRef, $schemeRef]
        );
    }

    public function findApplicableRules(string $franchiseRef, string $productRef, ?string $tierRef, string $date): array
    {
        return $this->db->fetchAll(
            "SELECT sr.*, s.scheme_name, s.priority, s.stacking_allowed, s.tier_ref as scheme_tier_ref, s.id as scheme_id
             FROM scheme_rules sr
             JOIN schemes s ON s.franchise_ref = sr.franchise_ref AND s.scheme_ref = sr.scheme_ref
             WHERE sr.franchise_ref = ?
               AND sr.product_ref = ?
               AND s.status = 'ACTIVE'
               AND s.start_date <= ?
               AND s.end_date >= ?
               AND (s.tier_ref IS NULL OR s.tier_ref = ?)
             ORDER BY s.priority ASC, s.id ASC, sr.min_qty ASC",
            [$franchiseRef, $productRef, $date, $date, $tierRef]
        );
    }

    public function createScheme(array $data): string
    {
        $this->db->insert('schemes', $data);
        return $data['scheme_ref'];
    }

    public function createRule(array $data): string
    {
        $this->db->insert('scheme_rules', $data);
        return $data['rule_ref'];
    }

    public function updateScheme(string $franchiseRef, string $schemeRef, array $data): bool
    {
        return $this->db->update(
            'schemes',
            $data,
            'franchise_ref = ? AND scheme_ref = ?',
            [$franchiseRef, $schemeRef]
        ) > 0;
    }
}
