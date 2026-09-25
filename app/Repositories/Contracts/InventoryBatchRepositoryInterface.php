<?php
declare(strict_types=1);
namespace App\Repositories\Contracts;

interface InventoryBatchRepositoryInterface
{
    public function findByRef(string $franchiseRef, string $batchRef): ?array;
    public function findByNo(string $franchiseRef, string $productRef, string $batchNo): ?array;
    public function getSaleableBatches(string $franchiseRef, string $productRef, int $minShelfDays = 0): array;
    public function create(array $data): string;
    public function updateQty(string $franchiseRef, string $batchRef, int $onHandDelta, int $reservedDelta, int $expectedVersion): bool;
    public function setStatus(string $franchiseRef, string $batchRef, string $status): bool;
    public function listNearExpiry(string $franchiseRef, int $days): array;
    public function list(string $franchiseRef, array $filters, int $page, int $perPage): array;
}
