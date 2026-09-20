<?php
declare(strict_types=1);
namespace App\Domain\Notifications\Adapters;

interface NotificationAdapterInterface
{
    /**
     * Send a rendered notification message.
     * Returns a provider message ID or tracking string on success.
     */
    public function send(string $recipient, string $message, array $meta = []): string;
}
