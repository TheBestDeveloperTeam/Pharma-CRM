<?php
declare(strict_types=1);
namespace App\Repositories\Contracts;

interface ProductCategoryRepositoryInterface
{
    public function list(string $franchiseRef, array $filters, int $page, int $perPage): array;
    public function findByRef(string $franchiseRef, string $categoryRef): ?array;
    public function findByName(string $franchiseRef, string $categoryName): ?array;
    public function create(array $data): string;
    public function update(string $franchiseRef, string $categoryRef, array $data): bool;
}
