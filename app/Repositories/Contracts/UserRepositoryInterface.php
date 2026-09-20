<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

interface UserRepositoryInterface
{
    public function findByRef(string $userRef): ?array;
    public function findByEmailAndTenant(string $email, string $tenantKey): ?array;
    public function list(array $filters, int $page, int $perPage): array;
    public function create(array $data): string;
    public function update(string $userRef, array $data): bool;
    public function setStatus(string $userRef, string $status): bool;
    public function unlock(string $userRef): bool;
    public function setPassword(string $userRef, string $passwordHash, bool $mustChangePassword = false): bool;
}
