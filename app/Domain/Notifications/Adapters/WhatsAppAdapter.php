<?php
declare(strict_types=1);
namespace App\Domain\Notifications\Adapters;

final class WhatsAppAdapter implements NotificationAdapterInterface
{
    public function send(string $recipient, string $message, array $meta = []): string
    {
        // Provider stub - in production reads WhatsApp Cloud API credentials or webhook config
        $providerMsgId = 'wa_' . bin2hex(random_bytes(10));
        return $providerMsgId;
    }
}
