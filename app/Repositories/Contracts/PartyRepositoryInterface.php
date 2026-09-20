<?php
declare(strict_types=1);
namespace App\Repositories\Contracts;

interface PartyRepositoryInterface
{
    public function list(string $franchiseRef, array $filters, int $page, int $perPage, ?string $salesUserRef = null): array;
    public function findByRef(string $franchiseRef, string $partyRef): ?array;
    public function findByCode(string $franchiseRef, string $partyCode): ?array;
    public function create(array $data): string;
    public function update(string $franchiseRef, string $partyRef, array $data): bool;
    public function setStatus(string $franchiseRef, string $partyRef, string $status): bool;
    public function getLedgerSummary(string $franchiseRef, string $partyRef): array;
}
