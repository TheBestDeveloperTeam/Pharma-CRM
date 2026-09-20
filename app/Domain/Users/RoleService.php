<?php

declare(strict_types=1);

namespace App\Domain\Users;

use App\Core\Exceptions\NotFoundException;
use App\Core\Exceptions\ConflictException;
use App\Core\Validation;
use App\Domain\Audit\AuditService;
use App\Repositories\Contracts\RoleRepositoryInterface;

/**
 * RoleService — Role and permission management.
 */
class RoleService
{
    public function __construct(
        private readonly RoleRepositoryInterface $roles,
        private readonly AuditService            $audit,
    ) {}

    public function listRoles(): array
    {
        return $this->roles->all();
    }

    public function listPermissions(): array
    {
        return $this->roles->allPermissions();
    }

    public function getRole(int $id): array
    {
        $role = $this->roles->find($id);
        if (!$role) throw new NotFoundException("Role #$id not found.");
        $role['permissions'] = $this->roles->getPermissionsForRole($id);
        return $role;
    }

    public function createRole(array $data, int $actorId): array
    {
        (new Validation($data, [
            'name' => 'required|string|maxLength:100',
            'slug' => 'required|string|maxLength:100',
        ]))->validate();

        if ($this->roles->findBySlug($data['slug'])) {
            throw new ConflictException("Role '{$data['slug']}' already exists.", 'ROLE_SLUG_CONFLICT');
        }

        $id = $this->roles->create([
            'name'        => $data['name'],
            'slug'        => strtolower($data['slug']),
            'description' => $data['description'] ?? null,
            'created_at'  => date('Y-m-d H:i:s'),
            'created_by'  => $actorId,
        ]);

        $this->audit->log('role.created', 'role', (int)$id, null, $data, ['actor' => $actorId]);

        return $this->getRole((int)$id);
    }

    public function assignPermissionToRole(int $roleId, int $permissionId, int $actorId): void
    {
        $role = $this->roles->find($roleId);
        if (!$role) throw new NotFoundException("Role #$roleId not found.");

        $this->roles->assignPermission($roleId, $permissionId);
        $this->audit->log('role.permission_assigned', 'role', $roleId, null, ['permission_id' => $permissionId], ['actor' => $actorId]);
    }

    public function revokePermissionFromRole(int $roleId, int $permissionId, int $actorId): void
    {
        $this->roles->revokePermission($roleId, $permissionId);
        $this->audit->log('role.permission_revoked', 'role', $roleId, null, ['permission_id' => $permissionId], ['actor' => $actorId]);
    }
}
