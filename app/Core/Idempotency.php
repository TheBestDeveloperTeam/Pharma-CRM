<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Idempotency — Stores and checks idempotency keys to prevent duplicate mutations.
 */
class Idempotency
{
    public function __construct(private readonly Database $db) {}

    /**
     * Check if a key has already been processed.
     * Returns the cached response body if found, or null.
     */
    public function check(string $key): ?array
    {
        $row = $this->db->fetchOne(
            'SELECT response_body FROM api_idempotency_keys WHERE idempotency_key = ? AND expires_at > NOW()',
            [$key]
        );
        if ($row && $row['response_body']) {
            return json_decode($row['response_body'], true);
        }
        return null;
    }

    /**
     * Store a processed key with its response.
     */
    public function store(string $key, array $response, int $ttlSeconds = 86400): void
    {
        $expiresAt = date('Y-m-d H:i:s', time() + $ttlSeconds);
        $this->db->execute(
            'INSERT INTO api_idempotency_keys (idempotency_key, response_body, expires_at, created_at)
             VALUES (?, ?, ?, NOW())
             ON DUPLICATE KEY UPDATE response_body = VALUES(response_body), expires_at = VALUES(expires_at)',
            [$key, json_encode($response), $expiresAt]
        );
    }
}
