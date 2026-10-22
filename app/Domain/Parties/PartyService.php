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

        // Auto-generate party code if not supplied or if colliding
        if (empty($data['party_code'])) {
            $attempts = 0;
            do {
                $num = $this->sequences->nextNumber($data['org_ref'], $franchiseRef, 'PARTY');
                $candidateCode = 'PTY-' . str_pad((string)$num, 5, '0', STR_PAD_LEFT);
                $attempts++;
            } while ($this->parties->findByCode($franchiseRef, $candidateCode) && $attempts < 20);

            // Ultimate safety fallback if table has fragmented/manual codes
            if ($this->parties->findByCode($franchiseRef, $candidateCode)) {
                $candidateCode = 'PTY-' . strtoupper(bin2hex(random_bytes(3)));
            }

            $data['party_code'] = $candidateCode;
        } else {
            // Check uniqueness within franchise
            $existing = $this->parties->findByCode($franchiseRef, $data['party_code']);
            if ($existing) {
                throw new ConflictException('PARTY_CODE_EXISTS', "Party code [{$data['party_code']}] is already in use for this franchise.");
            }
        }

        $data['party_ref'] = $data['party_ref'] ?? RefGenerator::generate('PAR');
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
