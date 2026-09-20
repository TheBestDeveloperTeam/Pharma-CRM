<?php
declare(strict_types=1);
namespace App\Repositories\Contracts;

interface TransporterRepositoryInterface
{
    public function list(string $franchiseRef, array $filters, int $page, int $perPage): array;
    public function findByRef(string $franchiseRef, string $transporterRef): ?array;
    public function findByName(string $franchiseRef, string $transporterName): ?array;
    public function create(array $data): string;
    public function update(string $franchiseRef, string $transporterRef, array $data): bool;
}
