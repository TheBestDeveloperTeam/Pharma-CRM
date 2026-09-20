<?php

declare(strict_types=1);

namespace App\Repositories\Sql;

use App\Core\Database;
use App\Repositories\Contracts\AuditRepositoryInterface;

class AuditRepository implements AuditRepositoryInterface
{
    public function __construct(private readonly Database $db) {}

    public function insert(array $data): void
    {
        $this->db->insert('audit_logs', $data);
    }

    public function findByRef(string $auditRef): ?array
    {
        return $this->db->fetchOne("SELECT * FROM audit_logs WHERE audit_ref = ?", [$auditRef]);
    }

    public function query(array $filters, int $page, int $perPage): array
    {
        $where  = ['1=1'];
        $params = [];

        if (!empty($filters['org_ref'])) {
            $where[]  = 'org_ref = ?';
            $params[] = $filters['org_ref'];
        }
        if (!empty($filters['franchise_ref'])) {
            $where[]  = 'franchise_ref = ?';
            $params[] = $filters['franchise_ref'];
        }
        if (!empty($filters['category'])) {
            $where[]  = 'category = ?';
            $params[] = $filters['category'];
        }
        if (!empty($filters['action'])) {
            $where[]  = 'action LIKE ?';
            $params[] = $filters['action'] . '%';
        }
        if (!empty($filters['entity_type'])) {
            $where[]  = 'entity_type = ?';
            $params[] = $filters['entity_type'];
        }
        if (!empty($filters['entity_ref'])) {
            $where[]  = 'entity_ref = ?';
            $params[] = $filters['entity_ref'];
        }
        if (!empty($filters['actor_ref'])) {
            $where[]  = 'actor_ref = ?';
            $params[] = $filters['actor_ref'];
        }

        $clause = implode(' AND ', $where);
        $offset = ($page - 1) * $perPage;

        $total = (int) $this->db->fetchColumn(
            "SELECT COUNT(*) FROM audit_logs WHERE $clause",
            $params
        );

        $rows = $this->db->fetchAll(
            "SELECT * FROM audit_logs WHERE $clause ORDER BY created_at DESC LIMIT ? OFFSET ?",
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
}
