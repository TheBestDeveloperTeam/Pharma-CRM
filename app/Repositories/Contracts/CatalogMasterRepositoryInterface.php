<?php
declare(strict_types=1);
namespace App\Repositories\Contracts;

interface CatalogMasterRepositoryInterface
{
    public function list(string $franchiseRef, string $masterKey, array $filters, int $page, int $perPage): array;
    public function findByRef(string $franchiseRef, string $masterRef): ?array;
    public function findByName(string $franchiseRef, string $masterKey, string $name): ?array;
    public function create(array $data): string;
    public function update(string $franchiseRef, string $masterRef, array $data): bool;
}
