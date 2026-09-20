<?php
declare(strict_types=1);
namespace App\Repositories\Contracts;

interface PricingTierRepositoryInterface
{
    public function list(string $franchiseRef, array $filters, int $page, int $perPage): array;
    public function findByRef(string $franchiseRef, string $tierRef): ?array;
    public function findByName(string $franchiseRef, string $tierName): ?array;
    public function create(array $data): string;
    public function update(string $franchiseRef, string $tierRef, array $data): bool;
}
