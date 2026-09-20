<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

interface RoleRepositoryInterface
{
    public function all(): array;
    public function allPermissions(): array;
    public function find(int $id): ?array;
    public function findBySlug(string $slug): ?array;
    public function create(array $data): int|string;
    public function update(int $id, array $data): void;
    public function getPermissionsForRole(int $roleId): array;
    public function assignPermission(int $roleId, int $permissionId): void;
    public function revokePermission(int $roleId, int $permissionId): void;
}
