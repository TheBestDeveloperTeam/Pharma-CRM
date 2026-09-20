<?php
declare(strict_types=1);
namespace App\Domain\Notifications;

use App\Core\Database;
use App\Core\RefGenerator;
use App\Domain\Notifications\Adapters\{NotificationAdapterInterface, LogAdapter, EmailAdapter, WhatsAppAdapter};

final class NotificationService
{
    public function __construct(
        private Database $db,
        private ?NotificationAdapterInterface $logAdapter = null,
        private ?NotificationAdapterInterface $emailAdapter = null,
        private ?NotificationAdapterInterface $whatsAppAdapter = null,
    ) {
        $this->logAdapter ??= new LogAdapter();
        $this->emailAdapter ??= new EmailAdapter();
        $this->whatsAppAdapter ??= new WhatsAppAdapter();
    }

    /**
     * Send or queue a notification with deduplication/idempotency.
     */
    public function send(
        string $orgRef,
        string $franchiseRef,
        string $eventType,
        string $channel, // INAPP, WHATSAPP, EMAIL, SMS
        string $message,
        ?string $userRef = null,
        ?string $partyRef = null,
        ?string $entityType = null,
        ?string $entityRef = null,
        array $payload = []
    ): ?string {
        // Idempotency key per event, entity_ref, channel, user/party
        $idemInput = implode(':', [$eventType, $entityRef ?? 'none', $channel, $userRef ?? $partyRef ?? 'all']);
        $idempotencyKey = hash('sha256', $idemInput);

        // Check if already sent / queued
        $existing = $this->db->fetchOne(
            "SELECT notification_ref, status FROM notifications 
             WHERE franchise_ref = :f AND idempotency_key = :k LIMIT 1",
            [':f' => $franchiseRef, ':k' => $idempotencyKey]
        );

        if ($existing) {
            return $existing['notification_ref'];
        }

        $notifRef = RefGenerator::generate('NTF');
        $providerMsgId = null;
        $status = 'SENT';

        try {
            if ($channel === 'EMAIL' && !empty($payload['email'])) {
                $providerMsgId = $this->emailAdapter->send((string)$payload['email'], $message, $payload);
            } elseif ($channel === 'WHATSAPP' && !empty($payload['phone'])) {
                $providerMsgId = $this->whatsAppAdapter->send((string)$payload['phone'], $message, $payload);
            } elseif ($channel === 'INAPP') {
                $status = 'QUEUED';
            } else {
                $providerMsgId = $this->logAdapter->send($userRef ?? $partyRef ?? 'system', $message, ['channel' => $channel]);
            }
        } catch (\Throwable $e) {
            $status = 'FAILED';
        }

        $sql = "INSERT INTO notifications (
            notification_ref, org_ref, franchise_ref, user_ref, party_ref,
            channel, event_type, entity_type, entity_ref, payload_json,
            status, provider_message_id, idempotency_key, created_at
        ) VALUES (
            :ref, :org, :frn, :user, :party,
            :channel, :evt, :etype, :eref, :payload,
            :status, :prov_id, :idem, NOW()
        )";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':ref'      => $notifRef,
            ':org'      => $orgRef,
            ':frn'      => $franchiseRef,
            ':user'     => $userRef,
            ':party'    => $partyRef,
            ':channel'  => $channel,
            ':evt'      => $eventType,
            ':etype'    => $entityType,
            ':eref'     => $entityRef,
            ':payload'  => json_encode(array_merge($payload, ['message' => $message]), JSON_UNESCAPED_SLASHES),
            ':status'   => $status,
            ':prov_id'  => $providerMsgId,
            ':idem'     => $idempotencyKey
        ]);

        return $notifRef;
    }

    /**
     * List in-app notifications for the active user / party.
     */
    public function listInApp(string $franchiseRef, ?string $userRef, ?string $partyRef, int $limit = 30): array
    {
        $params = [':f' => $franchiseRef];
        $where = "franchise_ref = :f AND channel = 'INAPP'";

        if ($userRef !== null) {
            $where .= " AND (user_ref = :u OR user_ref IS NULL)";
            $params[':u'] = $userRef;
        } elseif ($partyRef !== null) {
            $where .= " AND (party_ref = :p OR party_ref IS NULL)";
            $params[':p'] = $partyRef;
        }

        $sql = "SELECT notification_ref, event_type, entity_type, entity_ref, payload_json, status, created_at, read_at
                FROM notifications
                WHERE {$where}
                ORDER BY created_at DESC
                LIMIT " . (int)$limit;

        $rows = $this->db->fetchAll($sql, $params);
        foreach ($rows as &$r) {
            $r['payload'] = json_decode($r['payload_json'] ?? '{}', true);
            unset($r['payload_json']);
        }
        return $rows;
    }

    /**
     * Mark a notification as READ.
     */
    public function markAsRead(string $franchiseRef, string $notificationRef, ?string $userRef = null): bool
    {
        $sql = "UPDATE notifications 
                SET status = 'READ', read_at = NOW()
                WHERE franchise_ref = :f AND notification_ref = :ref";
        $params = [':f' => $franchiseRef, ':ref' => $notificationRef];
        if ($userRef !== null) {
            $sql .= " AND (user_ref = :u OR user_ref IS NULL)";
            $params[':u'] = $userRef;
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->rowCount() > 0;
    }

    /**
     * Mark all notifications as READ for user.
     */
    public function markAllAsRead(string $franchiseRef, ?string $userRef = null, ?string $partyRef = null): int
    {
        $params = [':f' => $franchiseRef];
        $where = "franchise_ref = :f AND status != 'READ' AND channel = 'INAPP'";

        if ($userRef !== null) {
            $where .= " AND user_ref = :u";
            $params[':u'] = $userRef;
        } elseif ($partyRef !== null) {
            $where .= " AND party_ref = :p";
            $params[':p'] = $partyRef;
        }

        $stmt = $this->db->prepare("UPDATE notifications SET status = 'READ', read_at = NOW() WHERE {$where}");
        $stmt->execute($params);
        return $stmt->rowCount();
    }
}
