<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

interface TerritoryRepositoryInterface
{
    public function findById(int $id): ?array;
    public function paginate(array $filters, int $page, int $perPage): array;
    public function create(array $data): int|string;
    public function update(int $id, array $data): void;
    public function getTree(): array;
}
