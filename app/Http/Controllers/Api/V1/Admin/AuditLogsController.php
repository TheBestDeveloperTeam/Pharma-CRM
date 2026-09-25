<?php
declare(strict_types=1);
namespace App\Http\Controllers\Api\V1\Admin;

use App\Core\{Database,Request,Response,TenantContext};
use App\Domain\Authorization\AuthorizationService;

/** Sensitive audit viewer: tenant-qualified and fail-closed for TERRITORY scope. */
final class AuditLogsController
{
    public function __construct(private Database $db, private AuthorizationService $authorization) {}

    public function index(Request $r): Response
    {
        $ctx = TenantContext::get(); $this->authorization->requirePermission($ctx, 'auditLogs', 'view');
        $page=max(1,(int)$r->query('page',1)); $per=min(100,max(1,(int)$r->query('per_page',25))); $params=[':f'=>$ctx->requireFranchise()]; $where=['franchise_ref=:f'];
        $scope=$ctx->scopeFor('auditLogs');
        if (!$ctx->isSuper() && $scope==='NONE') return Response::json(200,[],['page'=>$page,'per_page'=>$per,'total'=>0,'total_pages'=>0]);
        if (!$ctx->isSuper() && $scope==='OWN') {$where[]='actor_ref=:actor';$params[':actor']=$ctx->userRef;}
        if (!$ctx->isSuper() && $scope==='TEAM') {$actors=array_values(array_unique(array_merge([$ctx->userRef],$ctx->teamUserRefs)));if(!$actors)return Response::json(200,[],['page'=>$page,'per_page'=>$per,'total'=>0,'total_pages'=>0]);$marks=[];foreach($actors as$i=>$actor){$key=':actor'.$i;$marks[]=$key;$params[$key]=$actor;}$where[]='actor_ref IN ('.implode(',',$marks).')';}
        if (!$ctx->isSuper() && $scope==='TERRITORY') return Response::json(200,[],['page'=>$page,'per_page'=>$per,'total'=>0,'total_pages'=>0]);
        foreach(['category','entity_type','actor_ref'] as $key) if($r->query($key)!==''){$where[]="$key=:$key";$params[":$key"]=$r->query($key);}
        if($r->query('search')!==''){$where[]='(action LIKE :search OR entity_ref LIKE :search)';$params[':search']='%'.$r->query('search').'%';}
        $sql=implode(' AND ',$where);$total=(int)$this->db->fetchColumn("SELECT COUNT(*) FROM audit_logs WHERE $sql",$params);$offset=($page-1)*$per;
        $rows=$this->db->fetchAll("SELECT audit_ref,actor_ref,actor_role,category,action,entity_type,entity_ref,reason,created_at FROM audit_logs WHERE $sql ORDER BY created_at DESC LIMIT $per OFFSET $offset",$params);
        return Response::json(200,$rows,['page'=>$page,'per_page'=>$per,'total'=>$total,'total_pages'=>(int)ceil($total/$per)]);
    }
}
