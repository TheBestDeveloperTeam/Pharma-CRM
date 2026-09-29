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
        foreach(['category','entity_type','actor_ref'] as $key) {
            $val = $r->query($key);
            if ($val !== null && $val !== '') {
                $where[] = "$key=:$key";
                $params[":$key"] = (string)$val;
            }
        }
        $search = $r->query('search');
        if ($search !== null && $search !== '') {
            $where[] = '(action LIKE :search OR entity_ref LIKE :search)';
            $params[':search'] = '%' . (string)$search . '%';
        }
        $sql=implode(' AND ',$where);$total=(int)$this->db->fetchColumn("SELECT COUNT(*) FROM audit_logs WHERE $sql",$params);$offset=($page-1)*$per;
        $rows=$this->db->fetchAll("SELECT audit_ref,actor_ref,actor_role,category,action,entity_type,entity_ref,reason,created_at FROM audit_logs WHERE $sql ORDER BY created_at DESC LIMIT $per OFFSET $offset",$params);
        return Response::json(200,$rows,['page'=>$page,'per_page'=>$per,'total'=>$total,'total_pages'=>(int)ceil($total/$per)]);
    }

    public function show(Request $r, ?string $ref = null): Response
    {
        $ctx = TenantContext::get();
        $this->authorization->requirePermission($ctx, 'auditLogs', 'view');
        $ref = $ref ?? (string)$r->param('ref');

        $row = $this->db->fetchOne("SELECT * FROM audit_logs WHERE franchise_ref = :f AND audit_ref = :r LIMIT 1", [
            ':f' => $ctx->requireFranchise(),
            ':r' => $ref,
        ]);
        if (!$row) {
            throw new \App\Core\Exceptions\NotFoundException('AUDIT_NOT_FOUND', 'Audit log not found.');
        }

        $row['before'] = json_decode($row['before_json'] ?? '{}', true);
        $row['after'] = json_decode($row['after_json'] ?? '{}', true);
        unset($row['before_json'], $row['after_json']);

        return Response::json(200, $row);
    }

    public function export(Request $r): Response
    {
        $ctx = TenantContext::get();
        $this->authorization->requirePermission($ctx, 'auditLogs', 'view');
        $f = $ctx->requireFranchise();

        $rows = $this->db->fetchAll("SELECT audit_ref, actor_ref, actor_role, category, action, entity_type, entity_ref, reason, ip_address, created_at FROM audit_logs WHERE franchise_ref = :f ORDER BY created_at DESC LIMIT 2000", [':f' => $f]);

        $fh = fopen('php://temp', 'r+');
        if (!empty($rows)) {
            fputcsv($fh, array_keys(reset($rows)));
            foreach ($rows as $row) {
                fputcsv($fh, array_values($row));
            }
        }
        rewind($fh);
        $csv = stream_get_contents($fh);
        fclose($fh);

        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="audit-logs-' . date('Ymd-His') . '.csv"');
        echo $csv;
        exit(0);
    }

    public function entityAudit(Request $r): Response
    {
        $ctx = TenantContext::get();
        $this->authorization->requirePermission($ctx, 'auditLogs', 'view');
        $f = $ctx->requireFranchise();
        $type = (string)$r->param('entity_type');
        $ref = (string)$r->param('entity_ref');

        $rows = $this->db->fetchAll("SELECT audit_ref, actor_ref, actor_role, category, action, before_json, after_json, reason, created_at FROM audit_logs WHERE franchise_ref = :f AND entity_type = :t AND entity_ref = :r ORDER BY created_at DESC LIMIT 100", [
            ':f' => $f,
            ':t' => $type,
            ':r' => $ref,
        ]);

        foreach ($rows as &$row) {
            $row['before'] = json_decode($row['before_json'] ?? '{}', true);
            $row['after'] = json_decode($row['after_json'] ?? '{}', true);
            unset($row['before_json'], $row['after_json']);
        }

        return Response::json(200, $rows);
    }

    public function userAudit(Request $r): Response
    {
        $ctx = TenantContext::get();
        $this->authorization->requirePermission($ctx, 'auditLogs', 'view');
        $f = $ctx->requireFranchise();
        $userRef = (string)$r->param('user_ref');

        $rows = $this->db->fetchAll("SELECT audit_ref, category, action, entity_type, entity_ref, reason, ip_address, created_at FROM audit_logs WHERE franchise_ref = :f AND actor_ref = :u ORDER BY created_at DESC LIMIT 100", [
            ':f' => $f,
            ':u' => $userRef,
        ]);

        return Response::json(200, $rows);
    }

    public function securityEvents(Request $r): Response
    {
        $ctx = TenantContext::get();
        $this->authorization->requirePermission($ctx, 'auditLogs', 'view');
        $f = $ctx->requireFranchise();

        $rows = $this->db->fetchAll("SELECT audit_ref, actor_ref, actor_role, category, action, entity_type, entity_ref, reason, ip_address, user_agent, created_at FROM audit_logs WHERE franchise_ref = :f AND category IN ('SECURITY', 'TENANT_BYPASS') ORDER BY created_at DESC LIMIT 100", [
            ':f' => $f,
        ]);

        return Response::json(200, $rows);
    }
}
