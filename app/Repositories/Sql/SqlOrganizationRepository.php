<?php
declare(strict_types=1);
namespace App\Repositories\Sql;

use App\Core\Database;
use App\Repositories\Contracts\OrganizationRepositoryInterface;

final class SqlOrganizationRepository implements OrganizationRepositoryInterface
{
    public function __construct(private Database $db) {}

    public function list(array $filters, int $page, int $perPage): array
    {
        $where = ['1=1'];
        $params = [];

        if (!empty($filters['status'])) {
            $where[] = 'status = ?';
            $params[] = $filters['status'];
        }
        if (!empty($filters['search'])) {
            $where[] = '(org_name LIKE ? OR org_code LIKE ?)';
            $s = '%' . $filters['search'] . '%';
            $params[] = $s;
            $params[] = $s;
        }

        $clause = implode(' AND ', $where);
        $offset = ($page - 1) * $perPage;

        $total = (int) $this->db->fetchColumn(
            "SELECT COUNT(*) FROM organizations WHERE $clause",
            $params
        );

        $rows = $this->db->fetchAll(
            "SELECT * FROM organizations WHERE $clause ORDER BY created_at DESC LIMIT ? OFFSET ?",
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

    public function findByRef(string $orgRef): ?array
    {
        return $this->db->fetchOne("SELECT * FROM organizations WHERE org_ref = ? LIMIT 1", [$orgRef]);
    }

    public function findByCode(string $orgCode): ?array
    {
        return $this->db->fetchOne("SELECT * FROM organizations WHERE org_code = ? LIMIT 1", [$orgCode]);
    }

    public function create(array $data): string
    {
        $this->db->insert('organizations', $data);
        return $data['org_ref'];
    }

    public function update(string $orgRef, array $data): bool
    {
        return $this->db->update('organizations', $data, 'org_ref = ?', [$orgRef]) > 0;
    }

    public function setStatus(string $orgRef, string $status): bool
    {
        return $this->db->update('organizations', ['status' => $status], 'org_ref = ?', [$orgRef]) > 0;
    }
}
