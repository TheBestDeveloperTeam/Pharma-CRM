<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

interface AuditRepositoryInterface
{
    public function insert(array $data): void;
    public function findByRef(string $auditRef): ?array;
    public function query(array $filters, int $page, int $perPage): array;
}
