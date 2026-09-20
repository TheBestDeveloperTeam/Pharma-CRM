<?php

declare(strict_types=1);

namespace App\Repositories\Sql;

use App\Core\Database;
use App\Repositories\Contracts\RoleRepositoryInterface;

class RoleRepository implements RoleRepositoryInterface
{
    public function __construct(private readonly Database $db) {}

    public function all(): array
    {
        return $this->db->fetchAll("SELECT id, name, slug, description FROM roles ORDER BY name");
    }

    public function allPermissions(): array
    {
        return $this->db->fetchAll("SELECT id, name, slug, group_name FROM permissions ORDER BY group_name, name");
    }

    public function find(int $id): ?array
    {
        return $this->db->fetchOne("SELECT * FROM roles WHERE id = ?", [$id]);
    }

    public function findBySlug(string $slug): ?array
    {
        return $this->db->fetchOne("SELECT * FROM roles WHERE slug = ?", [$slug]);
    }

    public function create(array $data): int|string
    {
        return $this->db->insert('roles', $data);
    }

    public function update(int $id, array $data): void
    {
        $this->db->update('roles', $data, ['id' => $id]);
    }

    public function getPermissionsForRole(int $roleId): array
    {
        return $this->db->fetchAll(
            "SELECT p.id, p.name, p.slug, p.group_name
             FROM permissions p
             JOIN role_permissions rp ON rp.permission_id = p.id
             WHERE rp.role_id = ?
             ORDER BY p.group_name, p.name",
            [$roleId]
        );
    }

    public function assignPermission(int $roleId, int $permissionId): void
    {
        $this->db->execute(
            "INSERT IGNORE INTO role_permissions (role_id, permission_id, created_at) VALUES (?, ?, NOW())",
            [$roleId, $permissionId]
        );
    }

    public function revokePermission(int $roleId, int $permissionId): void
    {
        $this->db->execute(
            "DELETE FROM role_permissions WHERE role_id = ? AND permission_id = ?",
            [$roleId, $permissionId]
        );
    }
}
