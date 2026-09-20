<?php
declare(strict_types=1);
namespace App\Repositories\Contracts;

interface InventoryMovementRepositoryInterface
{
    public function record(array $data): string;
    public function listByBatch(string $franchiseRef, string $batchRef): array;
}
