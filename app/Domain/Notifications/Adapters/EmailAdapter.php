<?php
declare(strict_types=1);
namespace App\Domain\Notifications\Adapters;

final class EmailAdapter implements NotificationAdapterInterface
{
    public function send(string $recipient, string $message, array $meta = []): string
    {
        $subject = $meta['subject'] ?? 'Pharma CRM Notification';
        $headers = [
            'From: no-reply@crm.local',
            'Content-Type: text/plain; charset=UTF-8',
            'X-Mailer: PHP/' . phpversion()
        ];

        // In local / test env without sendmail, mail() may return false or fail quietly
        @mail($recipient, $subject, $message, implode("\r\n", $headers));
        return 'mail_' . bin2hex(random_bytes(8));
    }
}
