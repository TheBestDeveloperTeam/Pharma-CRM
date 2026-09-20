<?php
declare(strict_types=1);
namespace App\Domain\Territory;

use App\Core\Database;
use App\Repositories\Contracts\PartyTerritoryRepositoryInterface;

final class TerritoryValidator
{
    public const ALLOWED           = 'ALLOWED';
    public const BLOCKED_EXCLUSIVE = 'BLOCKED_EXCLUSIVE';
    public const BLOCKED           = 'BLOCKED';
    public const UNASSIGNED        = 'UNASSIGNED';

    public function __construct(
        private PartyTerritoryRepositoryInterface $territories,
        private Database $db,
    ) {}

    /**
     * Validate whether a party is authorized to receive/deliver in given pincode on a specific date
     */
    public function validate(string $franchiseRef, string $partyRef, string $pincode, ?string $date = null): array
    {
        $date = $date ?? date('Y-m-d');

        // 1. Resolve district for pincode
        $districtRef = (string)$this->db->fetchColumn(
            "SELECT district_ref FROM pincodes WHERE pincode = :pin LIMIT 1",
            [':pin' => $pincode]
        );

        // 2. Find all active parties serving this location
        $serving = $this->territories->findServingParties($franchiseRef, $pincode, $districtRef, $date);

        if (empty($serving)) {
            return [
                'status'  => self::ALLOWED, // When territory is unassigned, franchise allows general delivery unless strict setting
                'code'    => self::UNASSIGNED,
                'message' => 'No exclusive territory assigned for this location.',
            ];
        }

        // 3. Check if target party is among the servers
        $partyServes = false;
        $exclusiveHolder = null;

        foreach ($serving as $row) {
            if ($row['party_ref'] === $partyRef) {
                $partyServes = true;
            }
            if (!empty($row['is_exclusive'])) {
                $exclusiveHolder = $row;
            }
        }

        if ($partyServes) {
            return [
                'status'  => self::ALLOWED,
                'code'    => self::ALLOWED,
                'message' => 'Party has authorized territory for this destination.',
            ];
        }

        // If another party holds an exclusive territory here
        if ($exclusiveHolder !== null) {
            return [
                'status'           => self::BLOCKED,
                'code'             => self::BLOCKED_EXCLUSIVE,
                'message'          => "Destination {$pincode} is reserved exclusively by {$exclusiveHolder['firm_name']}.",
                'exclusive_party'  => $exclusiveHolder['party_ref'],
            ];
        }

        return [
            'status'  => self::ALLOWED,
            'code'    => self::ALLOWED,
            'message' => 'Non-exclusive territory destination.',
        ];
    }
}
