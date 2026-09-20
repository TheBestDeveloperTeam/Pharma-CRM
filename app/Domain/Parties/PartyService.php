<?php
declare(strict_types=1);
namespace App\Domain\Parties;

use App\Core\RefGenerator;
use App\Core\Exceptions\ConflictException;
use App\Core\Exceptions\NotFoundException;
use App\Repositories\Contracts\PartyRepositoryInterface;
use App\Core\SequenceService;

final class PartyService
{
    public function __construct(
        private PartyRepositoryInterface $parties,
        private SequenceService $sequences,
    ) {}

    public function create(array $data): string
    {
        $franchiseRef = $data['franchise_ref'];

        // Auto-generate party code if not supplied
        if (empty($data['party_code'])) {
            $num = $this->sequences->nextNumber($data['org_ref'], $franchiseRef, 'PARTY');
            $data['party_code'] = 'PTY-' . str_pad((string)$num, 5, '0', STR_PAD_LEFT);
        } else {
            // Check uniqueness within franchise
            $existing = $this->parties->findByCode($franchiseRef, $data['party_code']);
            if ($existing) {
                throw new ConflictException('PARTY_CODE_EXISTS', "Party code [{$data['party_code']}] is already in use for this franchise.");
            }
        }

        $data['party_ref'] = $data['party_ref'] ?? RefGenerator::generate('PTY');
        return $this->parties->create($data);
    }

    public function update(string $franchiseRef, string $partyRef, array $data): bool
    {
        $party = $this->parties->findByRef($franchiseRef, $partyRef);
        if (!$party) {
            throw new NotFoundException('PARTY_NOT_FOUND', 'Party not found.');
        }

        return $this->parties->update($franchiseRef, $partyRef, $data);
    }

    public function archive(string $franchiseRef, string $partyRef): bool
    {
        return $this->parties->setStatus($franchiseRef, $partyRef, 'ARCHIVED');
    }

    public function restore(string $franchiseRef, string $partyRef): bool
    {
        return $this->parties->setStatus($franchiseRef, $partyRef, 'ACTIVE');
    }
}
