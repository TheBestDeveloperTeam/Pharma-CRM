<?php
declare(strict_types=1);
namespace App\Repositories\Contracts;

interface FollowUpRepositoryInterface
{
    public function list(string $franchiseRef, array $filters, int $page, int $perPage, ?string $assignedUserRef = null): array;
    public function findByRef(string $franchiseRef, string $followupRef): ?array;
    public function create(array $data): string;
    public function update(string $franchiseRef, string $followupRef, array $data): bool;
    public function countPendingForLead(string $franchiseRef, string $leadRef): int;
}
