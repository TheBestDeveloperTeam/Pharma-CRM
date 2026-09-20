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

        $level = $data['level']; // PINCODE | DISTRICT
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
        return $this->territories->create($data);
    }

    public function createOverride(array $data): string
    {
        $data['override_ref'] = $data['override_ref'] ?? RefGenerator::generate('TOV');
        return $this->territories->createOverride($data);
    }
}
