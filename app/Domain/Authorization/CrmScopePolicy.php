<?php

declare(strict_types=1);

namespace App\Domain\Authorization;

use App\Core\{Database, TenantContext};

/**
 * B1 — data scope for Leads and Follow-ups, driven by the user's module scope
 * (auth_role_scopes / default_scope), never by the legacy role name.
 *
 *   ALL        → whole franchise
 *   OWN        → assigned_user_ref = me
 *   TEAM       → assigned to me or my direct reports (auth_user_hierarchy)
 *   TERRITORY  → leads: the lead's pincode/district is inside one of my
 *                territories; follow-ups: the linked lead or party is.
 *   NONE       → nothing
 * Super Admin always sees everything.
 *
 * Same semantics as Task009ScopePolicy / PartyScopePredicate, which already
 * scope onboarding, DCR, payments and orders this way. Every placeholder is
 * unique per statement (PDO runs with emulated prepares OFF).
 */
final class CrmScopePolicy
{
    public function __construct(private readonly Database $db) {}

    /** SQL predicate over the `leads` table (unaliased columns). */
    public function leadListClause(TenantContext $ctx, array &$params): string
    {
        return $this->clause($ctx, 'leads', $params, fn(array &$p): string => $this->leadTerritorySql($ctx, 'leads', $p, 'lt'));
    }

    /** SQL predicate over the `follow_ups` table (unaliased columns). */
    public function followUpListClause(TenantContext $ctx, array &$params): string
    {
        return $this->clause($ctx, 'followUps', $params, function (array &$p) use ($ctx): string {
            $viaLead = 'EXISTS (SELECT 1 FROM leads l WHERE l.franchise_ref = follow_ups.franchise_ref AND l.lead_ref = follow_ups.lead_ref AND '
                . $this->leadTerritorySql($ctx, 'l', $p, 'flt') . ')';
            $marks = $this->bindTerritories($ctx, $p, 'fpt');
            $viaParty = 'EXISTS (SELECT 1 FROM party_territories pt WHERE pt.franchise_ref = follow_ups.franchise_ref AND pt.party_ref = follow_ups.party_ref AND '
                . $this->activeTerritorySql('pt') . " AND pt.territory_ref IN ($marks))";
            return "($viaLead OR $viaParty)";
        });
    }

    /** Record check for one lead row (show / update / status). */
    public function canAccessLead(TenantContext $ctx, array $lead): bool
    {
        return $this->recordInScope($ctx, 'leads', $lead, function () use ($ctx, $lead): bool {
            return $this->leadInTerritory($ctx, $lead);
        });
    }

    /** Record check for one follow-up row (complete / reschedule). */
    public function canAccessFollowUp(TenantContext $ctx, array $followUp): bool
    {
        return $this->recordInScope($ctx, 'followUps', $followUp, function () use ($ctx, $followUp): bool {
            if (!empty($followUp['lead_ref'])) {
                $lead = $this->db->fetchOne('SELECT * FROM leads WHERE franchise_ref = ? AND lead_ref = ? LIMIT 1', [
                    $followUp['franchise_ref'] ?? '', 
                    $followUp['lead_ref']
                ]);
                if ($lead && $this->leadInTerritory($ctx, $lead)) return true;
            }
            if (!empty($followUp['party_ref'])) {
                $params = [':f' => (string)($followUp['franchise_ref'] ?? ''), ':p' => (string)$followUp['party_ref']];
                $marks = $this->bindTerritories($ctx, $params, 't');
                return (bool)$this->db->fetchColumn(
                    'SELECT 1 FROM party_territories pt WHERE pt.franchise_ref = :f AND pt.party_ref = :p AND ' . $this->activeTerritorySql('pt') . " AND pt.territory_ref IN ($marks) LIMIT 1",
                    $params
                );
            }
            return false;
        });
    }

    // ── internals ───────────────────────────────────────────────────────

    private function leadInTerritory(TenantContext $ctx, array $lead): bool
    {
        if (!$ctx->territoryRefs) return false;
        $params = [':f' => (string)$lead['franchise_ref'], ':pin' => (string)($lead['pincode'] ?? ''), ':district' => (string)($lead['district_ref'] ?? '')];
        $marks = $this->bindTerritories($ctx, $params, 't');
        return (bool)$this->db->fetchColumn(
            'SELECT 1 FROM party_territories pt WHERE pt.franchise_ref = :f AND ' . $this->activeTerritorySql('pt')
            . " AND ((pt.level = 'PINCODE' AND pt.pincode = :pin) OR (pt.level = 'DISTRICT' AND pt.district_ref = :district))"
            . " AND pt.territory_ref IN ($marks) LIMIT 1",
            $params
        );
    }

    private function recordInScope(TenantContext $ctx, string $module, array $record, callable $territoryCheck): bool
    {
        if ($ctx->isSuper()) return true;
        if (($record['franchise_ref'] ?? null) !== $ctx->franchiseRef) return false;
        
        $owner = $record['assigned_user_ref'] ?? null;
        $scope = $ctx->scopeFor($module);
        
        if ($scope === 'ALL') return true;
        if ($scope === 'OWN') return $owner === $ctx->userRef;
        if ($scope === 'TEAM') return $owner === $ctx->userRef || in_array($owner, $ctx->teamUserRefs, true);
        if ($scope === 'TERRITORY') return $territoryCheck();
        
        return false;
    }

    private function clause(TenantContext $ctx, string $module, array &$params, callable $territorySql): string
    {
        if ($ctx->isSuper()) return '1=1';
        $scope = $ctx->scopeFor($module);
        if ($scope === 'ALL') return '1=1';
        if ($scope === 'OWN') {
            $params[':scope_user'] = $ctx->userRef;
            return 'assigned_user_ref = :scope_user';
        }
        if ($scope === 'TEAM') {
            $marks = [];
            foreach (array_values(array_unique(array_merge([$ctx->userRef], $ctx->teamUserRefs))) as $i => $user) {
                $marks[] = ":scope_team_$i";
                $params[":scope_team_$i"] = $user;
            }
            return 'assigned_user_ref IN (' . implode(',', $marks) . ')';
        }
        if ($scope === 'TERRITORY') {
            if (!$ctx->territoryRefs) return '1=0';
            return $territorySql($params);
        }
        return '1=0';
    }

    /** Binds the user's territory refs under a unique prefix and returns the placeholder list. */
    private function bindTerritories(TenantContext $ctx, array &$params, string $prefix): string
    {
        $marks = [];
        foreach (array_values($ctx->territoryRefs) as $i => $territory) {
            $marks[] = ":{$prefix}_$i";
            $params[":{$prefix}_$i"] = $territory;
        }
        return $marks ? implode(',', $marks) : "''";
    }

    /** Territory predicate for a leads row aliased `$alias`. */
    private function leadTerritorySql(TenantContext $ctx, string $alias, array &$params, string $prefix): string
    {
        $marks = $this->bindTerritories($ctx, $params, $prefix);
        return "EXISTS (SELECT 1 FROM party_territories pt WHERE pt.franchise_ref = $alias.franchise_ref AND " . $this->activeTerritorySql('pt')
            . " AND ((pt.level = 'PINCODE' AND pt.pincode = $alias.pincode) OR (pt.level = 'DISTRICT' AND pt.district_ref = $alias.district_ref))"
            . " AND pt.territory_ref IN ($marks))";
    }

    private function activeTerritorySql(string $alias): string
    {
        return "$alias.status = 'ACTIVE' AND $alias.effective_from <= CURDATE() AND ($alias.effective_to IS NULL OR $alias.effective_to >= CURDATE())";
    }
}
