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
}
