<?php

declare(strict_types=1);

namespace App\Repositories\Sql;

use App\Core\Database;
use App\Repositories\Contracts\UserRepositoryInterface;

class UserRepository implements UserRepositoryInterface
{
    public function __construct(private readonly Database $db) {}

    public function findByRef(string $userRef): ?array
    {
        return $this->db->fetchOne(
            "SELECT * FROM users WHERE user_ref = ? LIMIT 1",
            [$userRef]
        );
    }

    public function findByEmailAndTenant(string $email, string $tenantKey): ?array
    {
        return $this->db->fetchOne(
            "SELECT * FROM users WHERE email = ? AND tenant_key = ? LIMIT 1",
            [strtolower(trim($email)), $tenantKey]
        );
    }

    public function list(array $filters, int $page, int $perPage): array
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
        if (!empty($filters['role'])) {
            $where[]  = 'role = ?';
            $params[] = $filters['role'];
        }
        if (!empty($filters['status'])) {
            $where[]  = 'status = ?';
            $params[] = $filters['status'];
        }
        if (!empty($filters['search'])) {
            $where[]  = '(full_name LIKE ? OR email LIKE ?)';
            $s        = '%' . $filters['search'] . '%';
            $params[] = $s;
            $params[] = $s;
        }

        $clause = implode(' AND ', $where);
        $offset = ($page - 1) * $perPage;

        $total = (int) $this->db->fetchColumn(
            "SELECT COUNT(*) FROM users WHERE $clause",
            $params
        );

        $rows = $this->db->fetchAll(
            "SELECT user_ref, org_ref, franchise_ref, role, party_ref, full_name, email, mobile,
                    must_change_password, status, last_login_at, failed_login_count, locked_until,
                    created_by_ref, created_at, updated_at
             FROM users WHERE $clause ORDER BY created_at DESC LIMIT ? OFFSET ?",
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

    public function create(array $data): string
    {
        $this->db->insert('users', $data);
        return $data['user_ref'];
    }

    public function update(string $userRef, array $data): bool
    {
        return $this->db->update('users', $data, 'user_ref = ?', [$userRef]) > 0;
    }

    public function setStatus(string $userRef, string $status): bool
    {
        return $this->db->update('users', ['status' => $status], 'user_ref = ?', [$userRef]) > 0;
    }

    public function unlock(string $userRef): bool
    {
        return $this->db->update('users', [
            'status'             => 'ACTIVE',
            'failed_login_count' => 0,
            'locked_until'       => null,
        ], 'user_ref = ?', [$userRef]) > 0;
    }

    public function setPassword(string $userRef, string $passwordHash, bool $mustChangePassword = false): bool
    {
        return $this->db->update('users', [
            'password_hash'        => $passwordHash,
            'must_change_password' => $mustChangePassword ? 1 : 0,
        ], 'user_ref = ?', [$userRef]) > 0;
    }
}
