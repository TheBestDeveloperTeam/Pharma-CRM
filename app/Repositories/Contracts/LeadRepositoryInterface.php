<?php
declare(strict_types=1);
namespace App\Repositories\Contracts;

interface LeadRepositoryInterface
{
    public function list(string $franchiseRef, array $filters, int $page, int $perPage, ?string $assignedUserRef = null): array;
    public function findByRef(string $franchiseRef, string $leadRef): ?array;
    public function findByMobile(string $franchiseRef, string $mobileNorm): ?array;
    public function create(array $data): string;
    public function update(string $franchiseRef, string $leadRef, array $data): bool;
    public function updateStatus(string $franchiseRef, string $leadRef, string $status, ?string $convertedPartyRef = null): bool;
    public function assign(string $franchiseRef, string $leadRef, ?string $userRef): bool;
    public function addActivity(string $franchiseRef, array $activity): string;
    public function getActivities(string $franchiseRef, string $leadRef): array;
}
