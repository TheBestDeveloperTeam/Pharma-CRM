<?php
declare(strict_types=1);
namespace App\Repositories\Contracts;

interface ProductPriceRepositoryInterface
{
    public function list(string $franchiseRef, array $filters, int $page, int $perPage): array;
    public function findByRef(string $franchiseRef, string $priceRef): ?array;
    public function findApplicable(string $franchiseRef, string $productRef, ?string $partyRef, ?string $tierRef, string $date): array;
    public function findOverlapping(string $franchiseRef, string $productRef, ?string $partyRef, ?string $tierRef, string $from, ?string $to, ?string $excludeRef = null): ?array;
    public function create(array $data): string;
    public function update(string $franchiseRef, string $priceRef, array $data): bool;
}
