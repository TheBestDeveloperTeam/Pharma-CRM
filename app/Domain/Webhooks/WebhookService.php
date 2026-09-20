<?php
declare(strict_types=1);
namespace App\Domain\Webhooks;

use App\Core\Database;
use App\Core\RefGenerator;
use App\Core\Exceptions\NotFoundException;
use App\Core\Exceptions\UnauthorizedException;
use App\Core\Exceptions\ValidationException;

final class WebhookService
{
    public function __construct(private Database $db) {}

    /**
     * Create a new webhook source with AES-256-GCM encrypted secret
     */
    public function createSource(string $orgRef, string $franchiseRef, string $sourceName, string $actorRef): array
    {
        $sourceRef = RefGenerator::generate('WHS');
        $endpointSlug = bin2hex(random_bytes(16)); // 32 chars unique
        $rawSecret = bin2hex(random_bytes(32));

        $appKey = $_ENV['APP_KEY'] ?? 'fallback_key_32_bytes_long_123456';
        $iv = random_bytes(12);
        $tag = '';
        $ciphertext = openssl_encrypt($rawSecret, 'aes-256-gcm', $appKey, OPENSSL_RAW_DATA, $iv, $tag);
        $secretEnc = $iv . $tag . $ciphertext;

        $sql = "INSERT INTO webhook_sources (
            source_ref, org_ref, franchise_ref, source_name, endpoint_slug, auth_type, secret_enc, created_by_ref
        ) VALUES (
            :source_ref, :org_ref, :franchise_ref, :source_name, :endpoint_slug, 'HMAC', :secret_enc, :created_by_ref
        )";

        $this->db->prepare($sql)->execute([
            ':source_ref'    => $sourceRef,
            ':org_ref'       => $orgRef,
            ':franchise_ref' => $franchiseRef,
            ':source_name'   => $sourceName,
            ':endpoint_slug' => $endpointSlug,
            ':secret_enc'    => $secretEnc,
            ':created_by_ref'=> $actorRef,
        ]);

        return [
            'source_ref'    => $sourceRef,
            'endpoint_slug' => $endpointSlug,
            'secret'        => $rawSecret, // Shown once
        ];
    }

    /**
     * Resolve source by endpoint slug and decrypt its HMAC secret
     */
    public function resolveSource(string $endpointSlug): array
    {
        $source = $this->db->fetchOne(
            "SELECT * FROM webhook_sources WHERE endpoint_slug = :slug AND status = 'ACTIVE' LIMIT 1",
            [':slug' => $endpointSlug]
        );

        if (!$source) {
            throw new NotFoundException('WEBHOOK_ENDPOINT_NOT_FOUND', 'Webhook endpoint not found or inactive.');
        }

        $appKey = $_ENV['APP_KEY'] ?? 'fallback_key_32_bytes_long_123456';
        $blob = $source['secret_enc'];
        $iv = substr($blob, 0, 12);
        $tag = substr($blob, 12, 16);
        $ciphertext = substr($blob, 28);

        $secret = openssl_decrypt($ciphertext, 'aes-256-gcm', $appKey, OPENSSL_RAW_DATA, $iv, $tag);
        if ($secret === false) {
            throw new \RuntimeException('Failed to decrypt webhook secret.');
        }

        $source['decrypted_secret'] = $secret;
        return $source;
    }

    /**
     * Verify HMAC and timestamp replay window
     */
    public function verifySignature(string $rawBody, string $secret, ?string $signatureHeader, ?int $timestamp): void
    {
        if (empty($signatureHeader)) {
            throw new UnauthorizedException('MISSING_SIGNATURE', 'Missing webhook signature header.');
        }

        // Check replay window (+-300 seconds)
        if ($timestamp !== null && abs(time() - $timestamp) > 300) {
            throw new ValidationException('REPLAY_DETECTED', 'Webhook timestamp expired or out of bounds.');
        }

        // Expected format: sha256=HEX
        $expectedHmac = hash_hmac('sha256', $rawBody, $secret);
        $provided = str_replace('sha256=', '', $signatureHeader);

        if (!hash_equals($expectedHmac, $provided)) {
            throw new UnauthorizedException('INVALID_SIGNATURE', 'Webhook signature verification failed.');
        }
    }
}
