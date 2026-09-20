<?php
declare(strict_types=1);
namespace App\Repositories\Contracts;

interface PaymentRepositoryInterface
{
    public function findByRef(string $franchiseRef, string $paymentRef): ?array;
    public function list(string $franchiseRef, array $filters, int $page, int $perPage, ?string $partyRef = null): array;
    public function create(array $data): string;
    public function allocate(string $franchiseRef, string $paymentRef, string $invoiceRef, float $amount): string;
    public function reverseAllocation(string $franchiseRef, string $allocationRef): bool;
}
