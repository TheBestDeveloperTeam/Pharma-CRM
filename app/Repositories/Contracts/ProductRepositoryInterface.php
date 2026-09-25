<?php
declare(strict_types=1);
namespace App\Repositories\Contracts;

interface ProductRepositoryInterface
{
    public function list(string $franchiseRef, array $filters, int $page, int $perPage): array;
    public function listActive(string $franchiseRef): array;
    public function findByRef(string $franchiseRef, string $productRef): ?array;
    public function findBySku(string $franchiseRef, string $sku): ?array;
    public function create(array $data): string;
    public function update(string $franchiseRef, string $productRef, array $data): bool;
    public function setStatus(string $franchiseRef, string $productRef, string $status): bool;
    public function isReferencedInOrders(string $franchiseRef, string $productRef): bool;
    public function isReferenced(string $franchiseRef, string $productRef): bool;
    public function delete(string $franchiseRef, string $productRef): bool;
}
