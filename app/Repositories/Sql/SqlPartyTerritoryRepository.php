<?php
declare(strict_types=1);
namespace App\Repositories\Sql;

use App\Core\Database;
use App\Repositories\Contracts\PartyTerritoryRepositoryInterface;

final class SqlPartyTerritoryRepository implements PartyTerritoryRepositoryInterface
{
    public function __construct(private Database $db) {}

    public function listForParty(string $franchiseRef, string $partyRef): array
    {
        return $this->db->fetchAll(
            "SELECT pt.*, d.district_name, s.state_name
             FROM party_territories pt
             LEFT JOIN districts d ON pt.district_ref = d.district_ref
             LEFT JOIN states s ON d.state_ref = s.state_ref
             WHERE pt.franchise_ref = :f AND pt.party_ref = :p
             ORDER BY pt.created_at DESC",
            [':f' => $franchiseRef, ':p' => $partyRef]
        );
    }

    public function findByRef(string $franchiseRef, string $territoryRef): ?array
    {
        return $this->db->fetchOne(
            "SELECT * FROM party_territories WHERE franchise_ref = :f AND territory_ref = :r LIMIT 1",
            [':f' => $franchiseRef, ':r' => $territoryRef]
        );
    }

    public function create(array $data): string
    {
        $sql = "INSERT INTO party_territories (
            territory_ref, org_ref, franchise_ref, party_ref, level,
            pincode, district_ref, effective_from, effective_to,
            is_exclusive, status, created_by_ref
        ) VALUES (
            :territory_ref, :org_ref, :franchise_ref, :party_ref, :level,
            :pincode, :district_ref, :effective_from, :effective_to,
            :is_exclusive, :status, :created_by_ref
        )";

        $this->db->prepare($sql)->execute([
            ':territory_ref' => $data['territory_ref'],
            ':org_ref'       => $data['org_ref'],
            ':franchise_ref' => $data['franchise_ref'],
            ':party_ref'     => $data['party_ref'],
            ':level'         => $data['level'],
            ':pincode'       => $data['pincode'] ?? null,
            ':district_ref'  => $data['district_ref'] ?? null,
            ':effective_from'=> $data['effective_from'],
            ':effective_to'  => $data['effective_to'] ?? null,
            ':is_exclusive'  => !empty($data['is_exclusive']) ? 1 : 0,
            ':status'        => $data['status'] ?? 'ACTIVE',
            ':created_by_ref'=> $data['created_by_ref'],
        ]);

        return $data['territory_ref'];
    }

    public function update(string $franchiseRef, string $territoryRef, array $data): bool
    {
        $allowed = ['effective_from', 'effective_to', 'is_exclusive', 'status'];
        $sets = [];
        $params = [':f' => $franchiseRef, ':r' => $territoryRef];

        foreach ($allowed as $f) {
            if (array_key_exists($f, $data)) {
                $sets[] = "{$f} = :{$f}";
                $params[":{$f}"] = $data[$f];
            }
        }

        if (empty($sets)) {
            return false;
        }

        $sql = "UPDATE party_territories SET " . implode(', ', $sets) . " WHERE franchise_ref = :f AND territory_ref = :r";
        return $this->db->prepare($sql)->execute($params);
    }

    public function checkExclusiveConflict(
        string $franchiseRef,
        string $level,
        string $locationVal,
        string $effectiveFrom,
        ?string $effectiveTo,
        ?string $excludePartyRef = null
    ): ?array {
        $where = [
            "franchise_ref = :f",
            "level = :lvl",
            "is_exclusive = 1",
            "status = 'ACTIVE'",
        ];
        $params = [
            ':f'   => $franchiseRef,
            ':lvl' => $level,
        ];

        if ($level === 'PINCODE') {
            $where[] = "pincode = :loc";
        } else {
            $where[] = "district_ref = :loc";
        }
        $params[':loc'] = $locationVal;

        if ($excludePartyRef !== null) {
            $where[] = "party_ref != :ex";
            $params[':ex'] = $excludePartyRef;
        }

        // Date overlap check:
        // (A_start <= B_end OR B_end IS NULL) AND (A_end >= B_start OR A_end IS NULL)
        if ($effectiveTo !== null) {
            $where[] = "effective_from <= :eff_to AND (effective_to >= :eff_from OR effective_to IS NULL)";
            $params[':eff_to'] = $effectiveTo;
            $params[':eff_from'] = $effectiveFrom;
        } else {
            $where[] = "(effective_to >= :eff_from OR effective_to IS NULL)";
            $params[':eff_from'] = $effectiveFrom;
        }

        $whereSql = implode(' AND ', $where);
        return $this->db->fetchOne("SELECT * FROM party_territories WHERE {$whereSql} LIMIT 1", $params);
    }

    public function findServingParties(string $franchiseRef, string $pincode, string $districtRef, string $date): array
    {
        // Check for parties mapped to this pincode or district on $date
        $sql = "SELECT pt.*, p.firm_name, p.party_code
                FROM party_territories pt
                JOIN parties p ON pt.franchise_ref = p.franchise_ref AND pt.party_ref = p.party_ref
                WHERE pt.franchise_ref = :f
                  AND pt.status = 'ACTIVE'
                  AND pt.effective_from <= :dt1
                  AND (pt.effective_to >= :dt2 OR pt.effective_to IS NULL)
                  AND (
                    (pt.level = 'PINCODE' AND pt.pincode = :pin)
                    OR
                    (pt.level = 'DISTRICT' AND pt.district_ref = :dist)
                  )";

        return $this->db->fetchAll($sql, [
            ':f'    => $franchiseRef,
            ':dt1'  => $date,
            ':dt2'  => $date,
            ':pin'  => $pincode,
            ':dist' => $districtRef,
        ]);
    }

    public function createOverride(array $data): string
    {
        $sql = "INSERT INTO territory_overrides (
            override_ref, org_ref, franchise_ref, order_ref, party_ref, pincode, reason, approved_by_ref
        ) VALUES (
            :override_ref, :org_ref, :franchise_ref, :order_ref, :party_ref, :pincode, :reason, :approved_by_ref
        )";

        $this->db->prepare($sql)->execute([
            ':override_ref'   => $data['override_ref'],
            ':org_ref'        => $data['org_ref'],
            ':franchise_ref'  => $data['franchise_ref'],
            ':order_ref'      => $data['order_ref'],
            ':party_ref'      => $data['party_ref'],
            ':pincode'        => $data['pincode'],
            ':reason'         => $data['reason'],
            ':approved_by_ref'=> $data['approved_by_ref'],
        ]);

        return $data['override_ref'];
    }
}
