<?php
declare(strict_types=1);
namespace App\Repositories\Contracts;

interface OrderRepositoryInterface
{
    public function findByRef(string $franchiseRef, string $orderRef): ?array;
    public function findByClientRef(string $franchiseRef, string $clientOrderRef): ?array;
    public function list(string $franchiseRef, array $filters, int $page, int $perPage, ?string $partyRef = null): array;
    public function create(array $orderData, array $items): string;
    public function updateStatus(string $franchiseRef, string $orderRef, string $status, string $actorRef, ?string $reason = null): bool;
    public function getItems(string $franchiseRef, string $orderRef): array;
    public function findByRefForUpdate(string $franchiseRef, string $orderRef): ?array;
    public function updateDraft(string $franchiseRef, string $orderRef, array $orderData, array $items, string $actorRef): bool;
    public function deleteDraft(string $franchiseRef, string $orderRef): bool;
    public function getHistory(string $franchiseRef, string $orderRef): array;
    public function updateStatusIfCurrent(string $franchiseRef, string $orderRef, string $fromStatus, string $toStatus, string $actorRef, ?string $reason = null): bool;
    public function updateStatusNoTransaction(string $franchiseRef, string $orderRef, string $fromStatus, string $toStatus, string $actorRef, ?string $reason = null): bool;
}
