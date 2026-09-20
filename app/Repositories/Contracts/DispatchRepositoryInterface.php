<?php
declare(strict_types=1);
namespace App\Repositories\Contracts;

interface DispatchRepositoryInterface
{
    public function findByRef(string $franchiseRef, string $dispatchRef): ?array;
    public function findByInvoiceRef(string $franchiseRef, string $invoiceRef): ?array;
    public function list(string $franchiseRef, array $filters, int $page, int $perPage): array;
    public function create(array $data): string;
    public function updateStatus(string $franchiseRef, string $dispatchRef, string $status): bool;
}
