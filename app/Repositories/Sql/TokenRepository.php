<?php

declare(strict_types=1);

namespace App\Repositories\Sql;

use App\Core\Database;
use App\Repositories\Contracts\TokenRepositoryInterface;

class TokenRepository implements TokenRepositoryInterface
{
    public function __construct(private readonly Database $db) {}

    public function store(int $userId, string $token, int $expiresAt): void
    {
        // Store hash of the refresh token (never store plain JWT)
        $tokenHash = hash('sha256', $token);
        $this->db->execute(
            "INSERT INTO api_tokens (user_id, token_hash, token_type, expires_at, created_at)
             VALUES (?, ?, 'refresh', FROM_UNIXTIME(?), NOW())
             ON DUPLICATE KEY UPDATE expires_at = FROM_UNIXTIME(?), revoked_at = NULL",
            [$userId, $tokenHash, $expiresAt, $expiresAt]
        );
    }

    public function isValid(string $token): bool
    {
        $tokenHash = hash('sha256', $token);
        $row = $this->db->fetchOne(
            "SELECT id FROM api_tokens
             WHERE token_hash = ? AND revoked_at IS NULL AND expires_at > NOW()",
            [$tokenHash]
        );
        return $row !== null;
    }

    public function revoke(string $token): void
    {
        $tokenHash = hash('sha256', $token);
        $this->db->execute(
            "UPDATE api_tokens SET revoked_at = NOW() WHERE token_hash = ?",
            [$tokenHash]
        );
    }

    public function revokeAllForUser(int $userId): void
    {
        $this->db->execute(
            "UPDATE api_tokens SET revoked_at = NOW() WHERE user_id = ? AND revoked_at IS NULL",
            [$userId]
        );
    }

    public function cleanExpired(): void
    {
        $this->db->execute(
            "DELETE FROM api_tokens WHERE expires_at < NOW() OR revoked_at < DATE_SUB(NOW(), INTERVAL 30 DAY)"
        );
    }
}
