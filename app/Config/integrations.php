<?php
declare(strict_types=1);

// Integrations configuration — external service adapters
return [
    // Webhook defaults
    'webhooks' => [
        'timeout'        => 10,       // HTTP timeout in seconds
        'max_retries'    => 3,
        'signing_algo'   => 'sha256', // HMAC signing algorithm
        'tolerance_secs' => 300,      // Timestamp tolerance for replay protection
    ],

    // SMTP email (used by EmailAdapter in NotificationService)
    'smtp' => [
        'host'       => $_ENV['SMTP_HOST'] ?? '',
        'port'       => (int)($_ENV['SMTP_PORT'] ?? 587),
        'encryption' => $_ENV['SMTP_ENCRYPTION'] ?? 'tls',
        'username'   => $_ENV['SMTP_USERNAME'] ?? '',
        'password'   => $_ENV['SMTP_PASSWORD'] ?? '',
        'from_email' => $_ENV['SMTP_FROM_EMAIL'] ?? 'noreply@pharmacrm.in',
        'from_name'  => $_ENV['SMTP_FROM_NAME'] ?? 'Pharma CRM',
    ],

    // WhatsApp Business API (stub — future implementation)
    'whatsapp' => [
        'enabled'      => false,
        'api_url'      => $_ENV['WHATSAPP_API_URL'] ?? '',
        'access_token' => $_ENV['WHATSAPP_TOKEN'] ?? '',
        'phone_id'     => $_ENV['WHATSAPP_PHONE_ID'] ?? '',
    ],

    // SMS Gateway (stub — future implementation)
    'sms' => [
        'enabled'  => false,
        'provider' => $_ENV['SMS_PROVIDER'] ?? 'log',   // log | textlocal | msg91
        'api_key'  => $_ENV['SMS_API_KEY'] ?? '',
        'sender'   => $_ENV['SMS_SENDER'] ?? 'PHARMA',
    ],
];
