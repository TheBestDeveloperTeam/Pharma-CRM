<?php
declare(strict_types=1);
namespace App\Repositories\Contracts;

interface InvoiceRepositoryInterface
{
    public function findByRef(string $franchiseRef, string $invoiceRef): ?array;
    public function findByOrderRef(string $franchiseRef, string $orderRef): ?array;
    public function list(string $franchiseRef, array $filters, int $page, int $perPage, ?string $partyRef = null): array;
    public function create(array $invoiceData, array $items): string;
    public function updatePaidTotal(string $franchiseRef, string $invoiceRef, float $paidTotal): bool;
    public function cancel(string $franchiseRef, string $invoiceRef, string $reason): bool;
}
