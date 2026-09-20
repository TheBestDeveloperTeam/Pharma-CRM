<?php
declare(strict_types=1);
namespace App\Http\Controllers\Api\V1\Super;

use App\Core\{Request, Response, Container, TenantContext, Database};
use App\Policies\SuperOrganizationPolicy;

final class SuperMetricsController
{
    public function __construct(private Database $db) {}

    private function authorize(): TenantContext
    {
        /** @var TenantContext $ctx */
        $ctx = Container::getInstance()->make(TenantContext::class);
        $policy = new SuperOrganizationPolicy($ctx);
        $policy->authorize('manage');
        return $ctx;
    }

    public function stats(Request $r): Response
    {
        $this->authorize();

        $orgCount = (int)$this->db->fetchColumn("SELECT COUNT(*) FROM organizations");
        $frnCount = (int)$this->db->fetchColumn("SELECT COUNT(*) FROM franchises");
        $userCount = (int)$this->db->fetchColumn("SELECT COUNT(*) FROM users");
        $pendingJobs = (int)$this->db->fetchColumn("SELECT COUNT(*) FROM job_queue WHERE status = 'PENDING'");
        $failedJobs = (int)$this->db->fetchColumn("SELECT COUNT(*) FROM job_queue WHERE status = 'DEAD'");
        $failedWebhooks = (int)$this->db->fetchColumn("SELECT COUNT(*) FROM webhook_events WHERE status = 'FAILED'");

        return Response::json(200, [
            'total_organizations' => $orgCount,
            'total_franchises'    => $frnCount,
            'total_users'         => $userCount,
            'pending_jobs'        => $pendingJobs,
            'failed_jobs'         => $failedJobs,
            'failed_webhooks'     => $failedWebhooks,
            'server_time'         => date('Y-m-d H:i:s'),
        ]);
    }

    public function audit(Request $r): Response
    {
        $this->authorize();

        $page = max(1, (int)$r->query('page', 1));
        $perPage = min(100, max(1, (int)$r->query('per_page', 25)));
        $offset = ($page - 1) * $perPage;

        $category = $r->query('category');
        $where = "1=1";
        $params = [];

        if (!empty($category)) {
            $where .= " AND category = :cat";
            $params[':cat'] = $category;
        }

        $total = (int)$this->db->fetchColumn("SELECT COUNT(*) FROM audit_logs WHERE {$where}", $params);
        $rows = $this->db->fetchAll(
            "SELECT audit_ref, org_ref, franchise_ref, actor_ref, actor_role, impersonator_ref,
                    category, action, entity_type, entity_ref, before_json, after_json, reason,
                    ip_address, request_id, created_at
             FROM audit_logs
             WHERE {$where}
             ORDER BY created_at DESC
             LIMIT {$perPage} OFFSET {$offset}",
            $params
        );

        foreach ($rows as &$row) {
            $row['before'] = json_decode($row['before_json'] ?? 'null', true);
            $row['after']  = json_decode($row['after_json'] ?? 'null', true);
            unset($row['before_json'], $row['after_json']);
        }

        return Response::json(200, $rows, [
            'page'     => $page,
            'per_page' => $perPage,
            'total'    => $total
        ]);
    }

    public function securityEvents(Request $r): Response
    {
        $this->authorize();

        $page = max(1, (int)$r->query('page', 1));
        $perPage = min(100, max(1, (int)$r->query('per_page', 25)));
        $offset = ($page - 1) * $perPage;

        $total = (int)$this->db->fetchColumn("SELECT COUNT(*) FROM audit_logs WHERE category IN ('SECURITY', 'TENANT_BYPASS')");
        $rows = $this->db->fetchAll(
            "SELECT audit_ref, org_ref, franchise_ref, actor_ref, actor_role, category, action,
                    entity_type, entity_ref, reason, ip_address, user_agent, request_id, created_at
             FROM audit_logs
             WHERE category IN ('SECURITY', 'TENANT_BYPASS')
             ORDER BY created_at DESC
             LIMIT {$perPage} OFFSET {$offset}"
        );

        return Response::json(200, $rows, [
            'page'     => $page,
            'per_page' => $perPage,
            'total'    => $total
        ]);
    }
}
