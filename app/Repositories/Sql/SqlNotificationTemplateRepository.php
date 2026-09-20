<?php
declare(strict_types=1);
namespace App\Repositories\Sql;

use App\Core\Database;
use App\Repositories\Contracts\NotificationTemplateRepositoryInterface;

final class SqlNotificationTemplateRepository implements NotificationTemplateRepositoryInterface
{
    public function __construct(private Database $db) {}

    public function list(string $franchiseRef): array
    {
        return $this->db->fetchAll(
            "SELECT * FROM notification_templates WHERE franchise_ref = ? ORDER BY event_type, channel",
            [$franchiseRef]
        );
    }

    public function find(string $franchiseRef, string $eventType, string $channel): ?array
    {
        return $this->db->fetchOne(
            "SELECT * FROM notification_templates WHERE franchise_ref = ? AND event_type = ? AND channel = ? LIMIT 1",
            [$franchiseRef, $eventType, $channel]
        );
    }

    public function save(array $data): bool
    {
        $existing = $this->find($data['franchise_ref'], $data['event_type'], $data['channel']);
        if ($existing) {
            return $this->db->update(
                'notification_templates',
                [
                    'body_template' => $data['body_template'],
                    'status'        => $data['status'] ?? 'ACTIVE',
                ],
                'franchise_ref = ? AND event_type = ? AND channel = ?',
                [$data['franchise_ref'], $data['event_type'], $data['channel']]
            ) > 0;
        }

        $this->db->insert('notification_templates', $data);
        return true;
    }
}
