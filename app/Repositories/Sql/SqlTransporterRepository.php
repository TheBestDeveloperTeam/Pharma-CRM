<?php
declare(strict_types=1);
namespace App\Repositories\Sql;

use App\Core\Database;
use App\Repositories\Contracts\TransporterRepositoryInterface;

final class SqlTransporterRepository implements TransporterRepositoryInterface
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
            $where[] = 'transporter_name LIKE ?';
            $params[] = '%' . $filters['search'] . '%';
        }

        $clause = implode(' AND ', $where);
        $offset = ($page - 1) * $perPage;

        $total = (int) $this->db->fetchColumn(
            "SELECT COUNT(*) FROM transporters WHERE $clause",
            $params
        );

        $rows = $this->db->fetchAll(
            "SELECT * FROM transporters WHERE $clause ORDER BY transporter_name ASC LIMIT ? OFFSET ?",
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

    public function findByRef(string $franchiseRef, string $transporterRef): ?array
    {
        return $this->db->fetchOne(
            "SELECT * FROM transporters WHERE franchise_ref = ? AND transporter_ref = ? LIMIT 1",
            [$franchiseRef, $transporterRef]
        );
    }

    public function findByName(string $franchiseRef, string $transporterName): ?array
    {
        return $this->db->fetchOne(
            "SELECT * FROM transporters WHERE franchise_ref = ? AND transporter_name = ? LIMIT 1",
            [$franchiseRef, $transporterName]
        );
    }

    public function create(array $data): string
    {
        $this->db->insert('transporters', $data);
        return $data['transporter_ref'];
    }

    public function update(string $franchiseRef, string $transporterRef, array $data): bool
    {
        return $this->db->update(
            'transporters',
            $data,
            'franchise_ref = ? AND transporter_ref = ?',
            [$franchiseRef, $transporterRef]
        ) > 0;
    }
}
