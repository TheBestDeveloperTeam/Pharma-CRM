<?php
declare(strict_types=1);
namespace App\Domain\Notifications\Adapters;

final class LogAdapter implements NotificationAdapterInterface
{
    public function __construct(private string $logFile = '')
    {
        if (empty($this->logFile)) {
            $this->logFile = dirname(__DIR__, 4) . '/storage/logs/notifications.log';
        }
    }

    public function send(string $recipient, string $message, array $meta = []): string
    {
        $dir = dirname($this->logFile);
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }

        $id = 'notif_log_' . bin2hex(random_bytes(8));
        $entry = json_encode([
            'id'        => $id,
            'timestamp' => date('Y-m-d H:i:s'),
            'channel'   => $meta['channel'] ?? 'LOG',
            'recipient' => $recipient,
            'message'   => $message,
            'meta'      => $meta
        ], JSON_UNESCAPED_SLASHES) . PHP_EOL;

        file_put_contents($this->logFile, $entry, FILE_APPEND | LOCK_EX);
        return $id;
    }
}
