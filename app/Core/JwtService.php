<?php

declare(strict_types=1);

namespace App\Core;

/**
 * JwtService — RS256 JWT generation and verification.
 *
 * No third-party library. Pure PHP implementation.
 * Algorithm: RS256 (RSA + SHA-256)
 */
class JwtService
{
    public function __construct(
        private readonly string $algo,
        private readonly string $privateKeyPath,
        private readonly string $publicKeyPath,
        private readonly int    $accessTtl,
        private readonly int    $refreshTtl,
    ) {}

    // ── Token Generation ──────────────────────────────────────────────────────

    public function issueAccessToken(array $payload): string
    {
        return $this->encode(array_merge($payload, [
            'typ' => 'access',
            'iat' => time(),
            'exp' => time() + $this->accessTtl,
        ]));
    }

    public function issueRefreshToken(array $payload): string
    {
        return $this->encode(array_merge($payload, [
            'typ' => 'refresh',
            'iat' => time(),
            'exp' => time() + $this->refreshTtl,
        ]));
    }

    // ── Verification ──────────────────────────────────────────────────────────

    /**
     * @throws \RuntimeException on invalid token
     */
    public function verify(string $token): array
    {
        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            throw new \RuntimeException('Invalid JWT structure.');
        }

        [$headerB64, $payloadB64, $signatureB64] = $parts;

        $this->verifySignature("$headerB64.$payloadB64", $signatureB64);

        $payload = json_decode($this->base64UrlDecode($payloadB64), true);

        if (!is_array($payload)) {
            throw new \RuntimeException('Invalid JWT payload.');
        }

        if (isset($payload['exp']) && $payload['exp'] < time()) {
            throw new \RuntimeException('JWT has expired.');
        }

        return $payload;
    }

    public function decode(string $token): ?array
    {
        try {
            return $this->verify($token);
        } catch (\Throwable) {
            return null;
        }
    }

    // ── Internals ─────────────────────────────────────────────────────────────

    private function encode(array $payload): string
    {
        $header = $this->base64UrlEncode(json_encode([
            'alg' => $this->algo,
            'typ' => 'JWT',
        ]));

        $claims = $this->base64UrlEncode(json_encode($payload));

        $data      = "$header.$claims";
        $signature = $this->sign($data);

        return "$data.$signature";
    }

    private function sign(string $data): string
    {
        $privateKey = $this->loadPrivateKey();
        openssl_sign($data, $signature, $privateKey, OPENSSL_ALGO_SHA256);
        return $this->base64UrlEncode($signature);
    }

    private function verifySignature(string $data, string $signatureB64): void
    {
        $signature = $this->base64UrlDecode($signatureB64);
        $publicKey = $this->loadPublicKey();
        $result    = openssl_verify($data, $signature, $publicKey, OPENSSL_ALGO_SHA256);

        if ($result !== 1) {
            throw new \RuntimeException('JWT signature verification failed.');
        }
    }

    private function loadPrivateKey(): \OpenSSLAsymmetricKey
    {
        if (!file_exists($this->privateKeyPath)) {
            throw new \RuntimeException("JWT private key not found: {$this->privateKeyPath}");
        }
        $key = openssl_pkey_get_private(file_get_contents($this->privateKeyPath));
        if ($key === false) {
            throw new \RuntimeException('Failed to load JWT private key.');
        }
        return $key;
    }

    private function loadPublicKey(): \OpenSSLAsymmetricKey
    {
        if (!file_exists($this->publicKeyPath)) {
            throw new \RuntimeException("JWT public key not found: {$this->publicKeyPath}");
        }
        $key = openssl_pkey_get_public(file_get_contents($this->publicKeyPath));
        if ($key === false) {
            throw new \RuntimeException('Failed to load JWT public key.');
        }
        return $key;
    }

    private function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    private function base64UrlDecode(string $data): string
    {
        return base64_decode(strtr($data, '-_', '+/') . str_repeat('=', (4 - strlen($data) % 4) % 4));
    }

    // ── Key Management Helpers ────────────────────────────────────────────────

    /**
     * Generate RS256 key pair and save to configured paths.
     * Call from CLI: php cli/generate-keys.php
     */
    public function generateKeyPair(): void
    {
        $config = [
            'digest_alg'       => 'sha256',
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ];

        $res = openssl_pkey_new($config);
        if (!$res) {
            throw new \RuntimeException('Failed to generate key pair.');
        }

        // Private key
        openssl_pkey_export($res, $privateKey);
        $privateDir = dirname($this->privateKeyPath);
        if (!is_dir($privateDir)) mkdir($privateDir, 0700, true);
        file_put_contents($this->privateKeyPath, $privateKey);
        chmod($this->privateKeyPath, 0600);

        // Public key
        $details = openssl_pkey_get_details($res);
        file_put_contents($this->publicKeyPath, $details['key']);
        chmod($this->publicKeyPath, 0644);
    }

    public function getAccessTtl(): int  { return $this->accessTtl; }
    public function getRefreshTtl(): int { return $this->refreshTtl; }
}
