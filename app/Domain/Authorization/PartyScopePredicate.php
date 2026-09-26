<?php
declare(strict_types=1);
namespace App\Domain\Authorization;
use App\Core\TenantContext;
/** Shared SQL predicate for a Party-owned record; TEAM is self plus TASK-001 direct reports. */
final class PartyScopePredicate {
 public function clause(TenantContext$c,string$partyAlias,array&$p,string$module='payments'):string{if($c->isDistributor()){$p[':scope_party']=$c->partyRef;return "$partyAlias.party_ref=:scope_party";}$s=$c->scopeFor($module);if($c->isSuper()||$s==='ALL')return'1=1';if($s==='NONE')return'1=0';if($s==='OWN'){$p[':scope_user']=$c->userRef;return "$partyAlias.sales_user_ref=:scope_user";}if($s==='TEAM'){$u=array_values(array_unique(array_merge([$c->userRef],$c->teamUserRefs)));if(!$u)return'1=0';$q=[];foreach($u as$i=>$v){$k=":scope_team_$i";$p[$k]=$v;$q[]=$k;}return "$partyAlias.sales_user_ref IN(".implode(',',$q).')';}if($s==='TERRITORY'){$q=[];foreach($c->territoryRefs as$i=>$v){$k=":scope_territory_$i";$p[$k]=$v;$q[]=$k;}if(!$q)return'1=0';return "EXISTS(SELECT 1 FROM party_territories pt WHERE pt.franchise_ref=$partyAlias.franchise_ref AND pt.party_ref=$partyAlias.party_ref AND pt.status='ACTIVE' AND pt.effective_from<=CURDATE() AND (pt.effective_to IS NULL OR pt.effective_to>=CURDATE()) AND pt.territory_ref IN(".implode(',',$q).'))';}return'1=0';}
}
