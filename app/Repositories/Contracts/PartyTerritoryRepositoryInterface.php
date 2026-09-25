<?php
declare(strict_types=1);
namespace App\Repositories\Contracts;

interface PartyTerritoryRepositoryInterface
{
    public function listForParty(string $franchiseRef, string $partyRef): array;
    public function list(string $franchiseRef, array $filters, int $page, int $perPage): array;
    public function findByRef(string $franchiseRef, string $territoryRef): ?array;
    public function create(array $data): string;
    public function update(string $franchiseRef, string $territoryRef, array $data): bool;
    public function checkExclusiveConflict(string $franchiseRef, string $level, string $locationVal, string $effectiveFrom, ?string $effectiveTo, ?string $excludePartyRef = null): ?array;
    public function findServingParties(string $franchiseRef, string $pincode, string $districtRef, string $date): array;
    public function createOverride(array $data): string;
}
