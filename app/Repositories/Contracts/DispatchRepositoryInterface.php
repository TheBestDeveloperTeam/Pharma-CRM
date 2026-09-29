<?php
declare(strict_types=1);
namespace App\Repositories\Contracts;

interface DispatchRepositoryInterface
{
    public function findByRef(string $franchiseRef, string $dispatchRef): ?array;
    public function findByRefForUpdate(string $franchiseRef, string $dispatchRef): ?array;
    public function findByInvoiceRef(string $franchiseRef, string $invoiceRef): ?array;
    public function list(string $franchiseRef, array $filters, int $page, int $perPage): array;
    public function create(array $data): string;
    public function updateStatusIfCurrent(string $franchiseRef, string $dispatchRef, string $fromStatus, string $toStatus, ?string $deliveryRemarks = null): bool;
    public function history(string $franchiseRef, string $dispatchRef): array;
    public function updateFields(string $franchiseRef, string $dispatchRef, array $fields): bool;
    public function updateStatus(string $franchiseRef, string $dispatchRef, string $status, ?string $actorRef = null, ?string $remarks = null): bool;
    public function listPending(string $franchiseRef): array;
}
