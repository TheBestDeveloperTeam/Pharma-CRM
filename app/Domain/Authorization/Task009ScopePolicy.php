<?php
declare(strict_types=1);
namespace App\Domain\Authorization;

use App\Core\{Database,TenantContext};
use App\Core\Exceptions\NotFoundException;

/** TASK-009 scope adapter. Uses only TASK-001 hierarchy and territory assignments. */
final class Task009ScopePolicy
{
    public function __construct(private Database $db, private AuthorizationService $authorization) {}

    /** @param array<string,mixed> $record */
    public function assertOnboarding(TenantContext $ctx, array $record): void
    {
        $territory = $this->territoryForLocation($ctx, (string)($record['pincode'] ?? ''), (string)($record['district_ref'] ?? ''), null);
        $this->authorization->requireRecordScope($ctx, 'distributorOnboarding', $record['assigned_user_ref'] ?? null, $territory, $record['franchise_ref'] ?? null);
    }

    /** @param array<string,mixed> $record */
    public function assertDcr(TenantContext $ctx, array $record): void
    {
        $territory = $this->territoryForLocation($ctx, '', '', (string)($record['distributor_party_ref'] ?? ''));
        $this->authorization->requireRecordScope($ctx, 'dcr', $record['owner_user_ref'] ?? null, $territory, $record['franchise_ref'] ?? null);
    }

    /** @param array<string,mixed> $params */
    public function onboardingListClause(TenantContext $ctx, array &$params): string
    {
        return $this->listClause($ctx, 'distributorOnboarding', 'assigned_user_ref', 'franchise_ref', 'pincode', 'district_ref', null, $params);
    }

    /** @param array<string,mixed> $params */
    public function dcrListClause(TenantContext $ctx, array &$params): string
    {
        return $this->listClause($ctx, 'dcr', 'owner_user_ref', 'franchise_ref', null, null, 'distributor_party_ref', $params);
    }

    /** @param array<string,mixed> $params */
    private function listClause(TenantContext $ctx, string $module, string $ownerColumn, string $tenantColumn, ?string $pincodeColumn, ?string $districtColumn, ?string $partyColumn, array &$params): string
    {
        $scope = $ctx->scopeFor($module);
        if ($ctx->isSuper() || $scope === 'ALL') return '1=1';
        if ($scope === 'NONE') return '1=0';
        if ($scope === 'OWN') { $params[':scope_user'] = $ctx->userRef; return "$ownerColumn = :scope_user"; }
        if ($scope === 'TEAM') {
            $users = array_values(array_unique(array_merge([$ctx->userRef], $ctx->teamUserRefs)));
            if (!$users) return '1=0';
            $marks=[]; foreach ($users as $i=>$user) {$key=':scope_team_'.$i;$marks[]=$key;$params[$key]=$user;}
            return "$ownerColumn IN (" . implode(',', $marks) . ')';
        }
        if ($scope === 'TERRITORY') {
            if (!$ctx->territoryRefs) return '1=0';
            $marks=[]; foreach ($ctx->territoryRefs as $i=>$territory) {$key=':scope_territory_'.$i;$marks[]=$key;$params[$key]=$territory;}
            $location = $partyColumn !== null
                ? "pt.party_ref = $partyColumn"
                : "((pt.level='PINCODE' AND pt.pincode = $pincodeColumn) OR (pt.level='DISTRICT' AND pt.district_ref = $districtColumn))";
            return "EXISTS (SELECT 1 FROM party_territories pt WHERE pt.franchise_ref = $tenantColumn AND pt.status='ACTIVE' AND pt.effective_from <= CURDATE() AND (pt.effective_to IS NULL OR pt.effective_to >= CURDATE()) AND $location AND pt.territory_ref IN (" . implode(',', $marks) . '))';
        }
        return '1=0';
    }

    private function territoryForLocation(TenantContext $ctx, string $pincode, string $districtRef, ?string $partyRef): ?string
    {
        if ($ctx->scopeFor('distributorOnboarding') !== 'TERRITORY' && $ctx->scopeFor('dcr') !== 'TERRITORY') return null;
        if (!$ctx->territoryRefs) return null;
        $marks=[]; $params=[':f'=>$ctx->requireFranchise()]; foreach ($ctx->territoryRefs as $i=>$ref) {$key=':t'.$i;$marks[]=$key;$params[$key]=$ref;}
        $location = $partyRef ? 'pt.party_ref = :party' : "((pt.level='PINCODE' AND pt.pincode=:pin) OR (pt.level='DISTRICT' AND pt.district_ref=:district))";
        if ($partyRef) $params[':party']=$partyRef; else {$params[':pin']=$pincode;$params[':district']=$districtRef;}
        return $this->db->fetchColumn("SELECT pt.territory_ref FROM party_territories pt WHERE pt.franchise_ref=:f AND pt.status='ACTIVE' AND pt.effective_from<=CURDATE() AND (pt.effective_to IS NULL OR pt.effective_to>=CURDATE()) AND $location AND pt.territory_ref IN (".implode(',',$marks).') LIMIT 1',$params) ?: null;
    }
}
