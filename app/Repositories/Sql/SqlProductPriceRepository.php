<?php
declare(strict_types=1);
namespace App\Repositories\Sql;

use App\Core\Database;
use App\Repositories\Contracts\ProductPriceRepositoryInterface;

final class SqlProductPriceRepository implements ProductPriceRepositoryInterface
{
    public function __construct(private Database $db) {}

    public function list(string $franchiseRef, array $filters, int $page, int $perPage): array
    {
        $where = ['pp.franchise_ref = ?'];
        $params = [$franchiseRef];

        if (!empty($filters['product_ref'])) {
            $where[] = 'pp.product_ref = ?';
            $params[] = $filters['product_ref'];
        }
        if (!empty($filters['tier_ref'])) {
            $where[] = 'pp.tier_ref = ?';
            $params[] = $filters['tier_ref'];
        }
        if (!empty($filters['party_ref'])) {
            $where[] = 'pp.party_ref = ?';
            $params[] = $filters['party_ref'];
        }
        if (!empty($filters['status'])) {
            $where[] = 'pp.status = ?';
            $params[] = $filters['status'];
        }

        $clause = implode(' AND ', $where);
        $offset = ($page - 1) * $perPage;

        $total = (int) $this->db->fetchColumn(
            "SELECT COUNT(*) FROM product_prices pp WHERE $clause",
            $params
        );

        $rows = $this->db->fetchAll(
            "SELECT pp.*, p.product_name, p.sku, t.tier_name
             FROM product_prices pp
             JOIN products p ON p.franchise_ref = pp.franchise_ref AND p.product_ref = pp.product_ref
             LEFT JOIN pricing_tiers t ON t.franchise_ref = pp.franchise_ref AND t.tier_ref = pp.tier_ref
             WHERE $clause
             ORDER BY pp.priority ASC, pp.effective_from DESC
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

    public function findByRef(string $franchiseRef, string $priceRef): ?array
    {
        return $this->db->fetchOne(
            "SELECT * FROM product_prices WHERE franchise_ref = ? AND price_ref = ? LIMIT 1",
            [$franchiseRef, $priceRef]
        );
    }

    public function findApplicable(string $franchiseRef, string $productRef, ?string $partyRef, ?string $tierRef, string $date): array
    {
        return $this->db->fetchAll(
            "SELECT * FROM product_prices
             WHERE franchise_ref = ?
               AND product_ref = ?
               AND status = 'ACTIVE'
               AND effective_from <= ?
               AND (effective_to IS NULL OR effective_to >= ?)
             ORDER BY priority ASC, effective_from DESC, id DESC",
            [$franchiseRef, $productRef, $date, $date]
        );
    }

    public function create(array $data): string
    {
        $this->db->insert('product_prices', $data);
        return $data['price_ref'];
    }

    public function update(string $franchiseRef, string $priceRef, array $data): bool
    {
        return $this->db->update(
            'product_prices',
            $data,
            'franchise_ref = ? AND price_ref = ?',
            [$franchiseRef, $priceRef]
        ) > 0;
    }
}
