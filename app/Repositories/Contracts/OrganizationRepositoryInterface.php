<?php
declare(strict_types=1);
namespace App\Repositories\Contracts;

interface OrganizationRepositoryInterface
{
    public function list(array $filters, int $page, int $perPage): array;
    public function findByRef(string $orgRef): ?array;
    public function findByCode(string $orgCode): ?array;
    public function create(array $data): string;
    public function update(string $orgRef, array $data): bool;
    public function setStatus(string $orgRef, string $status): bool;
}
