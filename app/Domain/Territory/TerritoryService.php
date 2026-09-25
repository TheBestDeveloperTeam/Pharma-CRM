<?php
declare(strict_types=1);
namespace App\Domain\Territory;

use App\Core\RefGenerator;
use App\Core\Exceptions\ConflictException;
use App\Core\Exceptions\NotFoundException;
use App\Repositories\Contracts\PartyTerritoryRepositoryInterface;
use App\Repositories\Contracts\PartyRepositoryInterface;

final class TerritoryService
{
    public function __construct(
        private PartyTerritoryRepositoryInterface $territories,
        private PartyRepositoryInterface $parties,
    ) {}

    public function create(array $data): string
    {
        $franchiseRef = $data['franchise_ref'];
        $partyRef     = $data['party_ref'];

        $party = $this->parties->findByRef($franchiseRef, $partyRef);
        if (!$party) {
            throw new NotFoundException('PARTY_NOT_FOUND', 'Party not found.');
        }

        $level = strtoupper((string)$data['level']); // PINCODE | DISTRICT
        if (!in_array($level, ['PINCODE', 'DISTRICT'], true)) throw new \App\Core\Exceptions\ValidationException('INVALID_TERRITORY_LEVEL', 'level must be PINCODE or DISTRICT.');
        if (empty($data['effective_from']) || ($data['effective_to'] ?? null) !== null && $data['effective_to'] < $data['effective_from']) throw new \App\Core\Exceptions\ValidationException('INVALID_EFFECTIVE_DATES', 'effective_to must be on or after effective_from.');
        $locationVal = $level === 'PINCODE' ? ($data['pincode'] ?? '') : ($data['district_ref'] ?? '');
        $isExclusive = !empty($data['is_exclusive']);

        if ($isExclusive) {
            $conflict = $this->territories->checkExclusiveConflict(
                $franchiseRef,
                $level,
                $locationVal,
                $data['effective_from'],
                $data['effective_to'] ?? null,
                $partyRef
            );

            if ($conflict) {
                throw new ConflictException(
                    'TERRITORY_CONFLICT',
                    "Exclusive territory already allocated for {$level} [{$locationVal}] to party {$conflict['party_ref']}."
                );
            }
        }

        $data['territory_ref'] = $data['territory_ref'] ?? RefGenerator::generate('TER');
        $data['level'] = $level;
        return $this->territories->create($data);
    }

    public function update(string $franchiseRef, string $territoryRef, array $data): bool
    {
        $existing = $this->territories->findByRef($franchiseRef, $territoryRef);
        if (!$existing) throw new NotFoundException('TERRITORY_NOT_FOUND', 'Territory allocation not found.');
        if (isset($data['effective_from'], $data['effective_to']) && $data['effective_to'] !== null && $data['effective_to'] < $data['effective_from']) throw new \App\Core\Exceptions\ValidationException('INVALID_EFFECTIVE_DATES', 'effective_to must be on or after effective_from.');
        if (!empty($data['is_exclusive'])) {
            $location = $existing['level'] === 'PINCODE' ? $existing['pincode'] : $existing['district_ref'];
            $conflict = $this->territories->checkExclusiveConflict($franchiseRef, $existing['level'], $location, $data['effective_from'] ?? $existing['effective_from'], $data['effective_to'] ?? $existing['effective_to'], $existing['party_ref']);
            if ($conflict) throw new ConflictException('TERRITORY_CONFLICT', 'Exclusive territory overlaps another party allocation.');
        }
        return $this->territories->update($franchiseRef, $territoryRef, $data);
    }

    public function createOverride(array $data): string
    {
        $data['override_ref'] = $data['override_ref'] ?? RefGenerator::generate('TOV');
        return $this->territories->createOverride($data);
    }
}
