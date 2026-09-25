<?php
declare(strict_types=1);
namespace App\Repositories\Sql;

use App\Core\Database;
use App\Repositories\Contracts\FranchiseRepositoryInterface;

final class SqlFranchiseRepository implements FranchiseRepositoryInterface
{
    public function __construct(private Database $db) {}

    public function list(array $filters, int $page, int $perPage): array
    {
        $where = ['1=1'];
        $params = [];

        if (!empty($filters['org_ref'])) {
            $where[] = 'org_ref = ?';
            $params[] = $filters['org_ref'];
        }
        if (!empty($filters['status'])) {
            $where[] = 'status = ?';
            $params[] = $filters['status'];
        }
        if (!empty($filters['search'])) {
            $where[] = '(franchise_name LIKE ? OR franchise_code LIKE ?)';
            $s = '%' . $filters['search'] . '%';
            $params[] = $s;
            $params[] = $s;
        }

        $clause = implode(' AND ', $where);
        $offset = ($page - 1) * $perPage;

        $total = (int) $this->db->fetchColumn(
            "SELECT COUNT(*) FROM franchises WHERE $clause",
            $params
        );

        $rows = $this->db->fetchAll(
            "SELECT * FROM franchises WHERE $clause ORDER BY created_at DESC LIMIT ? OFFSET ?",
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

    public function findByRef(string $franchiseRef): ?array
    {
        return $this->db->fetchOne("SELECT * FROM franchises WHERE franchise_ref = ? LIMIT 1", [$franchiseRef]);
    }

    public function findByCode(string $orgRef, string $franchiseCode): ?array
    {
        return $this->db->fetchOne(
            "SELECT * FROM franchises WHERE org_ref = ? AND franchise_code = ? LIMIT 1",
            [$orgRef, $franchiseCode]
        );
    }

    public function create(array $data): string
    {
        $this->db->insert('franchises', $data);
        return $data['franchise_ref'];
    }

    public function update(string $franchiseRef, array $data): bool
    {
        return $this->db->update('franchises', $data, 'franchise_ref = ?', [$franchiseRef]) > 0;
    }

    public function setStatus(string $franchiseRef, string $status): bool
    {
        return $this->db->update('franchises', ['status' => $status], 'franchise_ref = ?', [$franchiseRef]) > 0;
    }
}
