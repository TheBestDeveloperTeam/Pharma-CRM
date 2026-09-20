<?php
declare(strict_types=1);
namespace App\Repositories\Contracts;

interface FranchiseRepositoryInterface
{
    public function list(array $filters, int $page, int $perPage): array;
    public function findByRef(string $franchiseRef): ?array;
    public function findByCode(string $orgRef, string $franchiseCode): ?array;
    public function create(array $data): string;
    public function update(string $franchiseRef, array $data): bool;
    public function setStatus(string $franchiseRef, string $status): bool;
}
