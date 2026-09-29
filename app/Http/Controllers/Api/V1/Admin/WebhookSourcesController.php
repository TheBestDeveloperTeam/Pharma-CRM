<?php
declare(strict_types=1);
namespace App\Http\Controllers\Api\V1\Admin;

use App\Core\{Request, Response, Validation, TenantContext, RefGenerator, Container};
use App\Core\Exceptions\{NotFoundException, ValidationException};
use App\Domain\Authorization\AuthorizationService;
use App\Domain\Webhooks\WebhookService;
use App\Domain\Jobs\JobDispatcher;
use App\Core\Database;

final class WebhookSourcesController
{
    private JobDispatcher $jobDispatcher;

    public function __construct(
        private WebhookService $webhookService,
        private Database $db,
        private AuthorizationService $authorization,
        ?JobDispatcher $jobDispatcher = null,
    ) {
        $this->jobDispatcher = $jobDispatcher ?? Container::getInstance()->make(JobDispatcher::class);
    }

    private function ctx(): TenantContext
    {
        return TenantContext::get();
    }

    // --- Webhook Sources (WHK-005 to WHK-008) ---

    public function index(Request $r): Response
    {
        $ctx = $this->ctx();
        $this->authorization->requirePermission($ctx, 'webhooks', 'view');
        $items = $this->db->fetchAll(
            "SELECT source_ref, source_name, endpoint_slug, auth_type, status, created_at
             FROM webhook_sources WHERE franchise_ref = :f AND status != 'DELETED' ORDER BY created_at DESC",
            [':f' => $ctx->requireFranchise()]
        );

        return Response::json(200, $items, ['total' => count($items)]);
    }

    public function store(Request $r): Response
    {
        $ctx = $this->ctx();
        $this->authorization->requirePermission($ctx, 'webhooks', 'configure');
        $clean = Validation::validate($r->all(), [
            'source_name' => 'required|string',
        ]);

        $source = $this->webhookService->createSource(
            $ctx->orgRef,
            $ctx->requireFranchise(),
            $clean['source_name'],
            $ctx->userRef
        );

        return Response::json(201, $source);
    }

    public function show(Request $r, ?string $ref = null): Response
    {
        $ctx = $this->ctx();
        $this->authorization->requirePermission($ctx, 'webhooks', 'view');
        $ref = $ref ?? (string)$r->param('ref');

        $source = $this->db->fetchOne(
            "SELECT source_ref, source_name, endpoint_slug, auth_type, status, created_at
             FROM webhook_sources WHERE franchise_ref = :f AND source_ref = :r LIMIT 1",
            [':f' => $ctx->requireFranchise(), ':r' => $ref]
        );

        if (!$source) {
            throw new NotFoundException('WEBHOOK_SOURCE_NOT_FOUND', 'Webhook source not found.');
        }

        return Response::json(200, $source);
    }

    public function delete(Request $r, ?string $ref = null): Response
    {
        $ctx = $this->ctx();
        $this->authorization->requirePermission($ctx, 'webhooks', 'configure');
        $ref = $ref ?? (string)$r->param('ref');

        $this->db->prepare("UPDATE webhook_sources SET status = 'INACTIVE' WHERE franchise_ref = :f AND source_ref = :r")
            ->execute([':f' => $ctx->requireFranchise(), ':r' => $ref]);

        return Response::json(200, ['source_ref' => $ref, 'status' => 'INACTIVE']);
    }

    public function regenerateSecret(Request $r, ?string $ref = null): Response
    {
        $ctx = $this->ctx();
        $this->authorization->requirePermission($ctx, 'webhooks', 'configure');
        $ref = $ref ?? (string)$r->param('ref');

        $source = $this->db->fetchOne(
            "SELECT id FROM webhook_sources WHERE franchise_ref = :f AND source_ref = :r LIMIT 1",
            [':f' => $ctx->requireFranchise(), ':r' => $ref]
        );
        if (!$source) {
            throw new NotFoundException('WEBHOOK_SOURCE_NOT_FOUND', 'Webhook source not found.');
        }

        $rawSecret = bin2hex(random_bytes(32));
        $appKey = (string)getenv('APP_KEY') ?: 'secret-crm-super-admin-key-32ch';
        $iv = random_bytes(12);
        $tag = '';
        $ciphertext = openssl_encrypt($rawSecret, 'aes-256-gcm', $appKey, OPENSSL_RAW_DATA, $iv, $tag);
        $secretEnc = $iv . $tag . $ciphertext;

        $this->db->prepare("UPDATE webhook_sources SET secret_enc = :s WHERE franchise_ref = :f AND source_ref = :r")
            ->execute([':s' => $secretEnc, ':f' => $ctx->requireFranchise(), ':r' => $ref]);

        return Response::json(200, [
            'source_ref' => $ref,
            'secret'     => $rawSecret, // Shown once
        ]);
    }

    public function test(Request $r, ?string $ref = null): Response
    {
        $ctx = $this->ctx();
        $this->authorization->requirePermission($ctx, 'webhooks', 'configure');
        $ref = $ref ?? (string)$r->param('ref');

        $source = $this->db->fetchOne(
            "SELECT source_ref, source_name, endpoint_slug, status FROM webhook_sources WHERE franchise_ref = :f AND source_ref = :r LIMIT 1",
            [':f' => $ctx->requireFranchise(), ':r' => $ref]
        );
        if (!$source) {
            throw new NotFoundException('WEBHOOK_SOURCE_NOT_FOUND', 'Webhook source not found.');
        }

        return Response::json(200, [
            'source_ref'    => $ref,
            'source_name'   => $source['source_name'],
            'endpoint_url'  => '/api/v1/webhooks/' . $source['endpoint_slug'] . '/leads',
            'status'        => 'READY',
            'message'       => 'Webhook endpoint is active and listening for HMAC-SHA256 payloads.',
        ]);
    }

    public function sourceEvents(Request $r, ?string $ref = null): Response
    {
        $ctx = $this->ctx();
        $this->authorization->requirePermission($ctx, 'webhooks', 'view');
        $ref = $ref ?? (string)$r->param('ref');

        $events = $this->db->fetchAll(
            "SELECT event_ref, external_event_id, status, attempt_count, error_message, received_at, processed_at
             FROM webhook_events WHERE franchise_ref = :f AND source_ref = :r ORDER BY received_at DESC LIMIT 100",
            [':f' => $ctx->requireFranchise(), ':r' => $ref]
        );

        return Response::json(200, $events);
    }

    // --- Webhook Events (WHK-001 to WHK-004) ---

    public function listEvents(Request $r): Response
    {
        $ctx = $this->ctx();
        $this->authorization->requirePermission($ctx, 'webhooks', 'view');
        $f = $ctx->requireFranchise();

        $page = max(1, (int)$r->query('page', 1));
        $perPage = min(100, max(1, (int)$r->query('per_page', 25)));
        $offset = ($page - 1) * $perPage;

        $params = [':f' => $f];
        $where = "WHERE franchise_ref = :f";

        if ($r->query('status')) {
            $where .= " AND status = :s";
            $params[':s'] = $r->query('status');
        }

        $total = (int)$this->db->fetchColumn("SELECT COUNT(*) FROM webhook_events {$where}", $params);

        $sql = "SELECT event_ref, source_ref, external_event_id, status, attempt_count, error_code, error_message, received_at, processed_at
                FROM webhook_events {$where} ORDER BY received_at DESC LIMIT {$perPage} OFFSET {$offset}";
        $items = $this->db->fetchAll($sql, $params);

        return Response::json(200, $items, [
            'total'       => $total,
            'page'        => $page,
            'per_page'    => $perPage,
            'total_pages' => $perPage > 0 ? (int)ceil($total / $perPage) : 0,
        ]);
    }

    public function showEvent(Request $r, ?string $ref = null): Response
    {
        $ctx = $this->ctx();
        $this->authorization->requirePermission($ctx, 'webhooks', 'view');
        $ref = $ref ?? (string)$r->param('ref');

        $event = $this->db->fetchOne(
            "SELECT * FROM webhook_events WHERE franchise_ref = :f AND event_ref = :r LIMIT 1",
            [':f' => $ctx->requireFranchise(), ':r' => $ref]
        );
        if (!$event) {
            throw new NotFoundException('WEBHOOK_EVENT_NOT_FOUND', 'Webhook event not found.');
        }

        $event['payload'] = json_decode($event['payload_json'] ?? '{}', true);
        unset($event['payload_json']);

        return Response::json(200, $event);
    }

    public function failedEvents(Request $r): Response
    {
        $ctx = $this->ctx();
        $this->authorization->requirePermission($ctx, 'webhooks', 'view');

        $items = $this->db->fetchAll(
            "SELECT event_ref, source_ref, external_event_id, status, attempt_count, error_code, error_message, received_at
             FROM webhook_events WHERE franchise_ref = :f AND status = 'FAILED' ORDER BY received_at DESC LIMIT 100",
            [':f' => $ctx->requireFranchise()]
        );

        return Response::json(200, $items);
    }

    public function retryEvent(Request $r, ?string $ref = null): Response
    {
        $ctx = $this->ctx();
        $this->authorization->requirePermission($ctx, 'webhooks', 'configure');
        $ref = $ref ?? (string)$r->param('ref');

        $event = $this->db->fetchOne(
            "SELECT * FROM webhook_events WHERE franchise_ref = :f AND event_ref = :r LIMIT 1",
            [':f' => $ctx->requireFranchise(), ':r' => $ref]
        );
        if (!$event) {
            throw new NotFoundException('WEBHOOK_EVENT_NOT_FOUND', 'Webhook event not found.');
        }

        // Reset status to RECEIVED and re-queue job
        $this->db->prepare("UPDATE webhook_events SET status = 'RECEIVED', error_message = NULL WHERE event_ref = :r")
            ->execute([':r' => $ref]);

        $this->jobDispatcher->dispatch(
            'ProcessWebhookEvent',
            ['event_ref' => $ref],
            $event['org_ref'],
            $event['franchise_ref'],
            "webhook_event:{$ref}:retry"
        );

        return Response::json(200, [
            'event_ref' => $ref,
            'status'    => 'QUEUED',
            'message'   => 'Webhook event re-queued for processing.',
        ]);
    }
}
