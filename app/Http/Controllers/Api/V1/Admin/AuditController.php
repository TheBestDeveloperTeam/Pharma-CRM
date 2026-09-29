<?php
declare(strict_types=1);
namespace App\Http\Controllers\Api\V1\Admin;

use App\Core\{Request, Response, Container, TenantContext};
use App\Domain\Authorization\AuthorizationService;
use App\Core\Exceptions\NotFoundException;

final class AuditController
{
    private \PDO $pdo;
    private AuthorizationService $auth;

    public function __construct(
        ?\PDO $pdo = null,
        ?AuthorizationService $auth = null,
    ) {
        $this->pdo = $pdo ?? Container::getInstance()->make(\PDO::class);
        $this->auth = $auth ?? Container::getInstance()->make(AuthorizationService::class);
    }

    private function ctx(): TenantContext
    {
        $ctx = Container::getInstance()->make(TenantContext::class);
        $this->auth->requirePermission($ctx, 'audit', 'view');
        return $ctx;
    }

    public function index(Request $r): Response
    {
        $ctx = $this->ctx();
        $f = $ctx->requireFranchise();

        $page = max(1, (int)$r->query('page', 1));
        $perPage = min(100, max(1, (int)$r->query('per_page', 25)));
        $offset = ($page - 1) * $perPage;

        $params = [':f' => $f];
        $where = ["franchise_ref = :f"];

        if ($r->query('category')) {
            $where[] = "category = :cat";
            $params[':cat'] = $r->query('category');
        }
        if ($r->query('entity_type')) {
            $where[] = "entity_type = :etype";
            $params[':etype'] = $r->query('entity_type');
        }
        if ($r->query('actor_ref')) {
            $where[] = "actor_ref = :actor";
            $params[':actor'] = $r->query('actor_ref');
        }
        if ($r->query('from')) {
            $where[] = "created_at >= :from";
            $params[':from'] = $r->query('from') . ' 00:00:00';
        }
        if ($r->query('to')) {
            $where[] = "created_at <= :to";
            $params[':to'] = $r->query('to') . ' 23:59:59';
        }

        $wSql = implode(' AND ', $where);

        $stmtCount = $this->pdo->prepare("SELECT COUNT(*) FROM audit_logs WHERE {$wSql}");
        $stmtCount->execute($params);
        $total = (int)$stmtCount->fetchColumn();

        $stmt = $this->pdo->prepare("SELECT audit_ref, org_ref, franchise_ref, actor_ref, actor_role, impersonator_ref, category, action, entity_type, entity_ref, reason, ip_address, user_agent, request_id, created_at FROM audit_logs WHERE {$wSql} ORDER BY created_at DESC LIMIT :lim OFFSET :off");
        foreach ($params as $k => $v) {
            $stmt->bindValue($k, $v);
        }
        $stmt->bindValue(':lim', $perPage, \PDO::PARAM_INT);
        $stmt->bindValue(':off', $offset, \PDO::PARAM_INT);
        $stmt->execute();

        return Response::json(200, $stmt->fetchAll(\PDO::FETCH_ASSOC), [
            'total'       => $total,
            'page'        => $page,
            'per_page'    => $perPage,
            'total_pages' => $perPage > 0 ? (int)ceil($total / $perPage) : 0,
        ]);
    }

    public function show(Request $r, ?string $ref = null): Response
    {
        $ctx = $this->ctx();
        $ref = $ref ?? (string)$r->param('ref');

        $stmt = $this->pdo->prepare("SELECT * FROM audit_logs WHERE franchise_ref = :f AND audit_ref = :r LIMIT 1");
        $stmt->execute([':f' => $ctx->requireFranchise(), ':r' => $ref]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$row) {
            throw new NotFoundException('AUDIT_NOT_FOUND', 'Audit log not found.');
        }

        $row['before'] = json_decode($row['before_json'] ?? '{}', true);
        $row['after'] = json_decode($row['after_json'] ?? '{}', true);
        unset($row['before_json'], $row['after_json']);

        return Response::json(200, $row);
    }

    public function export(Request $r): Response
    {
        $ctx = $this->ctx();
        $f = $ctx->requireFranchise();

        $stmt = $this->pdo->prepare("SELECT audit_ref, actor_ref, actor_role, category, action, entity_type, entity_ref, reason, ip_address, created_at FROM audit_logs WHERE franchise_ref = :f ORDER BY created_at DESC LIMIT 2000");
        $stmt->execute([':f' => $f]);
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

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
        $ctx = $this->ctx();
        $f = $ctx->requireFranchise();
        $type = (string)$r->param('entity_type');
        $ref = (string)$r->param('entity_ref');

        $stmt = $this->pdo->prepare("SELECT audit_ref, actor_ref, actor_role, category, action, before_json, after_json, reason, created_at FROM audit_logs WHERE franchise_ref = :f AND entity_type = :t AND entity_ref = :r ORDER BY created_at DESC LIMIT 100");
        $stmt->execute([':f' => $f, ':t' => $type, ':r' => $ref]);
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        foreach ($rows as &$row) {
            $row['before'] = json_decode($row['before_json'] ?? '{}', true);
            $row['after'] = json_decode($row['after_json'] ?? '{}', true);
            unset($row['before_json'], $row['after_json']);
        }

        return Response::json(200, $rows);
    }

    public function userAudit(Request $r): Response
    {
        $ctx = $this->ctx();
        $f = $ctx->requireFranchise();
        $userRef = (string)$r->param('user_ref');

        $stmt = $this->pdo->prepare("SELECT audit_ref, category, action, entity_type, entity_ref, reason, ip_address, created_at FROM audit_logs WHERE franchise_ref = :f AND actor_ref = :u ORDER BY created_at DESC LIMIT 100");
        $stmt->execute([':f' => $f, ':u' => $userRef]);

        return Response::json(200, $stmt->fetchAll(\PDO::FETCH_ASSOC));
    }

    public function securityEvents(Request $r): Response
    {
        $ctx = $this->ctx();
        $f = $ctx->requireFranchise();

        $stmt = $this->pdo->prepare("SELECT audit_ref, actor_ref, actor_role, category, action, entity_type, entity_ref, reason, ip_address, user_agent, created_at FROM audit_logs WHERE franchise_ref = :f AND category IN ('SECURITY', 'TENANT_BYPASS') ORDER BY created_at DESC LIMIT 100");
        $stmt->execute([':f' => $f]);

        return Response::json(200, $stmt->fetchAll(\PDO::FETCH_ASSOC));
    }
}
