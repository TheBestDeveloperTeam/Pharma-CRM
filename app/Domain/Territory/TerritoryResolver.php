<?php
declare(strict_types=1);
namespace App\Domain\Territory;

use App\Core\Database;
use App\Repositories\Contracts\PartyTerritoryRepositoryInterface;

/** Centralized, read-only territory resolution. It reports facts only; Order policy is deferred. */
final class TerritoryResolver
{
    public const MATCHED = 'MATCHED';
    public const UNASSIGNED = 'UNASSIGNED';
    public const CONFLICT = 'CONFLICT';

    public function __construct(private PartyTerritoryRepositoryInterface $territories, private Database $db) {}

    public function resolve(string $franchiseRef, string $partyRef, string $pincode, ?string $districtRef = null, ?string $date = null): array
    {
        $date = $date ?: date('Y-m-d');
        if ($districtRef === null || $districtRef === '') {
            $districtRef = (string)$this->db->fetchColumn('SELECT district_ref FROM pincodes WHERE pincode = ? LIMIT 1', [$pincode]);
        }
        $serving = $this->territories->findServingParties($franchiseRef, $pincode, $districtRef, $date);
        if (!$serving) return ['status' => self::UNASSIGNED, 'code' => self::UNASSIGNED, 'pincode' => $pincode, 'district_ref' => $districtRef ?: null, 'effective_date' => $date];
        $holders = array_values(array_unique(array_column($serving, 'party_ref')));
        $exclusive = array_values(array_filter($serving, static fn(array $row): bool => !empty($row['is_exclusive'])));
        $matched = in_array($partyRef, $holders, true);
        if (count($exclusive) > 1 || (!$matched && $exclusive)) return ['status' => self::CONFLICT, 'code' => self::CONFLICT, 'party_ref' => $partyRef, 'exclusive_party_refs' => array_values(array_unique(array_column($exclusive, 'party_ref'))), 'pincode' => $pincode, 'district_ref' => $districtRef ?: null, 'effective_date' => $date];
        if ($matched && count($holders) === 1) return ['status' => self::MATCHED, 'code' => self::MATCHED, 'party_ref' => $partyRef, 'pincode' => $pincode, 'district_ref' => $districtRef ?: null, 'effective_date' => $date];
        return ['status' => self::CONFLICT, 'code' => self::CONFLICT, 'party_ref' => $partyRef, 'serving_party_refs' => $holders, 'pincode' => $pincode, 'district_ref' => $districtRef ?: null, 'effective_date' => $date];
    }
}
