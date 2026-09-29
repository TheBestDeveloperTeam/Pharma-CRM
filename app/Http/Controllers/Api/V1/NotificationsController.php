<?php
declare(strict_types=1);
namespace App\Http\Controllers\Api\V1;

use App\Core\{Request, Response, Container, TenantContext, Validation, RefGenerator};
use App\Domain\Notifications\NotificationService;
use App\Domain\Authorization\AuthorizationService;
use App\Core\Exceptions\{ForbiddenException, NotFoundException, ValidationException};

final class NotificationsController
{
    private \PDO $pdo;
    private AuthorizationService $auth;

    public function __construct(
        private NotificationService $notifService,
        ?\PDO $pdo = null,
        ?AuthorizationService $auth = null,
    ) {
        $this->pdo = $pdo ?? Container::getInstance()->make(\PDO::class);
        $this->auth = $auth ?? Container::getInstance()->make(AuthorizationService::class);
    }

    private function getCtx(): TenantContext
    {
        /** @var TenantContext $ctx */
        $ctx = Container::getInstance()->make(TenantContext::class);
        if (!$ctx->userRef && !$ctx->partyRef) {
            throw new ForbiddenException('UNAUTHENTICATED', 'Authentication required.');
        }
        return $ctx;
    }

    public function index(Request $r): Response
    {
        $ctx = $this->getCtx();
        $franchiseRef = $ctx->requireFranchise();
        $limit = min(50, max(1, (int)$r->query('limit', 20)));

        $list = $this->notifService->listInApp(
            $franchiseRef,
            $ctx->userRef,
            $ctx->partyRef,
            $limit
        );

        return Response::json(200, $list, [
            'total' => count($list),
            'limit' => $limit
        ]);
    }

    public function unreadCount(Request $r): Response
    {
        $ctx = $this->getCtx();
        $franchiseRef = $ctx->requireFranchise();
        $sql = "SELECT COUNT(*) FROM notifications WHERE franchise_ref = :f AND (user_ref = :u OR (user_ref IS NULL AND party_ref = :p)) AND status = 'SENT'";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':f' => $franchiseRef, ':u' => $ctx->userRef, ':p' => $ctx->partyRef]);
        return Response::json(200, ['unread_count' => (int)$stmt->fetchColumn()]);
    }

    public function markRead(Request $r, ?string $ref = null): Response
    {
        $ctx = $this->getCtx();
        $franchiseRef = $ctx->requireFranchise();
        $notifRef = $ref ?? (string)($r->params['ref'] ?? $r->param('ref'));

        $success = $this->notifService->markAsRead($franchiseRef, $notifRef, $ctx->userRef);

        return Response::json(200, [
            'notification_ref' => $notifRef,
            'read'             => $success
        ]);
    }

    public function markAllRead(Request $r): Response
    {
        $ctx = $this->getCtx();
        $franchiseRef = $ctx->requireFranchise();

        $count = $this->notifService->markAllAsRead($franchiseRef, $ctx->userRef, $ctx->partyRef);

        return Response::json(200, [
            'marked_count' => $count
        ]);
    }

    public function delete(Request $r, ?string $ref = null): Response
    {
        $ctx = $this->getCtx();
        $franchiseRef = $ctx->requireFranchise();
        $ref = $ref ?? (string)$r->param('ref');

        $stmt = $this->pdo->prepare("DELETE FROM notifications WHERE franchise_ref = :f AND notification_ref = :r");
        $stmt->execute([':f' => $franchiseRef, ':r' => $ref]);

        return Response::json(200, ['notification_ref' => $ref, 'deleted' => true]);
    }

    // --- Admin Notification Management (NTF-011, NTF-012) ---

    public function sendSystem(Request $r): Response
    {
        $ctx = $this->getCtx();
        $this->auth->requirePermission($ctx, 'notifications', 'send');
        $clean = Validation::validate($r->all(), [
            'event_type' => 'required|string',
            'message'    => 'required|string',
        ]);

        $channel = strtoupper((string)$r->input('channel', 'INAPP'));
        $notifRef = $this->notifService->send(
            orgRef: $ctx->orgRef,
            franchiseRef: $ctx->requireFranchise(),
            eventType: $clean['event_type'],
            channel: $channel,
            message: $clean['message'],
            userRef: $r->input('user_ref'),
            partyRef: $r->input('party_ref'),
            entityType: $r->input('entity_type'),
            entityRef: $r->input('entity_ref'),
            payload: $r->input('payload', [])
        );

        return Response::json(201, ['notification_ref' => $notifRef, 'status' => 'SENT']);
    }

    public function manageTemplates(Request $r): Response
    {
        $ctx = $this->getCtx();
        $this->auth->requirePermission($ctx, 'notifications', 'view');
        $stmt = $this->pdo->prepare("SELECT id, org_ref, franchise_ref, event_type, channel, body_template, status FROM notification_templates WHERE franchise_ref = :f ORDER BY event_type ASC");
        $stmt->execute([':f' => $ctx->requireFranchise()]);
        return Response::json(200, $stmt->fetchAll(\PDO::FETCH_ASSOC));
    }

    // --- WhatsApp Integration (NTF-003 to NTF-010) ---

    public function listWhatsAppMessages(Request $r): Response
    {
        $ctx = $this->getCtx();
        $this->auth->requirePermission($ctx, 'notifications', 'view');
        $f = $ctx->requireFranchise();

        $page = max(1, (int)$r->query('page', 1));
        $perPage = min(100, max(1, (int)$r->query('per_page', 25)));
        $offset = ($page - 1) * $perPage;

        $stmtCount = $this->pdo->prepare("SELECT COUNT(*) FROM notifications WHERE franchise_ref = :f AND channel = 'WHATSAPP'");
        $stmtCount->execute([':f' => $f]);
        $total = (int)$stmtCount->fetchColumn();

        $stmt = $this->pdo->prepare("SELECT notification_ref, event_type, entity_type, entity_ref, status, provider_message_id, created_at, read_at FROM notifications WHERE franchise_ref = :f AND channel = 'WHATSAPP' ORDER BY created_at DESC LIMIT :lim OFFSET :off");
        $stmt->bindValue(':f', $f);
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

    public function showWhatsAppMessage(Request $r, ?string $ref = null): Response
    {
        $ctx = $this->getCtx();
        $this->auth->requirePermission($ctx, 'notifications', 'view');
        $ref = $ref ?? (string)$r->param('ref');

        $stmt = $this->pdo->prepare("SELECT * FROM notifications WHERE franchise_ref = :f AND notification_ref = :r AND channel = 'WHATSAPP' LIMIT 1");
        $stmt->execute([':f' => $ctx->requireFranchise(), ':r' => $ref]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$row) {
            throw new NotFoundException('MESSAGE_NOT_FOUND', 'WhatsApp message record not found.');
        }

        $row['payload'] = json_decode($row['payload_json'] ?? '{}', true);
        unset($row['payload_json']);

        return Response::json(200, $row);
    }

    public function retryWhatsAppMessage(Request $r, ?string $ref = null): Response
    {
        $ctx = $this->getCtx();
        $this->auth->requirePermission($ctx, 'notifications', 'send');
        $ref = $ref ?? (string)$r->param('ref');

        $stmt = $this->pdo->prepare("UPDATE notifications SET status = 'QUEUED', attempt_count = attempt_count + 1 WHERE franchise_ref = :f AND notification_ref = :r AND channel = 'WHATSAPP'");
        $stmt->execute([':f' => $ctx->requireFranchise(), ':r' => $ref]);

        return Response::json(200, ['notification_ref' => $ref, 'status' => 'QUEUED', 'retried' => true]);
    }

    public function sendWhatsApp(Request $r): Response
    {
        $ctx = $this->getCtx();
        $this->auth->requirePermission($ctx, 'notifications', 'send');
        $clean = Validation::validate($r->all(), [
            'phone'      => 'required|string',
            'event_type' => 'required|string',
            'message'    => 'required|string',
        ]);

        $notifRef = $this->notifService->send(
            orgRef: $ctx->orgRef,
            franchiseRef: $ctx->requireFranchise(),
            eventType: $clean['event_type'],
            channel: 'WHATSAPP',
            message: $clean['message'],
            userRef: $r->input('user_ref'),
            partyRef: $r->input('party_ref'),
            entityType: $r->input('entity_type'),
            entityRef: $r->input('entity_ref'),
            payload: array_merge($r->input('payload', []), ['phone' => $clean['phone']])
        );

        return Response::json(201, ['notification_ref' => $notifRef, 'status' => 'SENT']);
    }

    public function listWhatsAppTemplates(Request $r): Response
    {
        $ctx = $this->getCtx();
        $this->auth->requirePermission($ctx, 'notifications', 'view');
        $stmt = $this->pdo->prepare("SELECT id, event_type, channel, body_template, status FROM notification_templates WHERE franchise_ref = :f AND channel = 'WHATSAPP' ORDER BY event_type ASC");
        $stmt->execute([':f' => $ctx->requireFranchise()]);
        return Response::json(200, $stmt->fetchAll(\PDO::FETCH_ASSOC));
    }

    public function createWhatsAppTemplate(Request $r): Response
    {
        $ctx = $this->getCtx();
        $this->auth->requirePermission($ctx, 'notifications', 'configure');
        $clean = Validation::validate($r->all(), [
            'event_type'    => 'required|string',
            'body_template' => 'required|string',
        ]);

        $stmt = $this->pdo->prepare("INSERT INTO notification_templates (org_ref, franchise_ref, event_type, channel, body_template, status) VALUES (:org, :f, :evt, 'WHATSAPP', :body, 'ACTIVE') ON DUPLICATE KEY UPDATE body_template = VALUES(body_template), status = 'ACTIVE'");
        $stmt->execute([
            ':org'  => $ctx->orgRef,
            ':f'    => $ctx->requireFranchise(),
            ':evt'  => strtoupper($clean['event_type']),
            ':body' => $clean['body_template'],
        ]);

        return Response::json(201, [
            'event_type'    => strtoupper($clean['event_type']),
            'channel'       => 'WHATSAPP',
            'body_template' => $clean['body_template'],
            'status'        => 'ACTIVE',
        ]);
    }

    public function updateWhatsAppTemplate(Request $r, ?string $ref = null): Response
    {
        $ctx = $this->getCtx();
        $this->auth->requirePermission($ctx, 'notifications', 'configure');
        $ref = $ref ?? (string)$r->param('ref');

        $clean = Validation::validate($r->all(), [
            'body_template' => 'required|string',
        ]);

        $stmt = $this->pdo->prepare("UPDATE notification_templates SET body_template = :body, status = COALESCE(:s, status) WHERE franchise_ref = :f AND (id = :ref OR event_type = :ref) AND channel = 'WHATSAPP'");
        $stmt->execute([
            ':body' => $clean['body_template'],
            ':s'    => $r->input('status'),
            ':f'    => $ctx->requireFranchise(),
            ':ref'  => $ref,
        ]);

        return Response::json(200, ['template_ref' => $ref, 'updated' => true]);
    }

    public function whatsAppDeliveryReport(Request $r): Response
    {
        $ctx = $this->getCtx();
        $this->auth->requirePermission($ctx, 'notifications', 'view');
        $stmt = $this->pdo->prepare("SELECT status, COUNT(*) as count FROM notifications WHERE franchise_ref = :f AND channel = 'WHATSAPP' GROUP BY status");
        $stmt->execute([':f' => $ctx->requireFranchise()]);
        return Response::json(200, $stmt->fetchAll(\PDO::FETCH_ASSOC));
    }
}
