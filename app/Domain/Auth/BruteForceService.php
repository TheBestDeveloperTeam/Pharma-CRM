<?php

declare(strict_types=1);

namespace App\Domain\Auth;

use App\Core\Database;
use App\Core\Exceptions\AuthenticationException;

/**
 * BruteForceService — Tracks failed login attempts and enforces lockouts.
 */
class BruteForceService
{
    private int $maxAttempts;
    private int $lockoutMinutes;

    public function __construct(private readonly Database $db)
    {
        $this->maxAttempts    = (int) env('BRUTE_MAX_ATTEMPTS', 5);
        $this->lockoutMinutes = (int) env('BRUTE_LOCKOUT_MINUTES', 30);
    }

    public function checkLockout(string $email, string $ip): void
    {
        $since = date('Y-m-d H:i:s', time() - ($this->lockoutMinutes * 60));

        $attempts = (int) $this->db->fetchColumn(
            "SELECT COUNT(*) FROM login_attempts
             WHERE (identifier = ? OR ip_address = ?) AND success = 0 AND attempted_at >= ?",
            [$email, $ip, $since]
        );

        if ($attempts >= $this->maxAttempts) {
            throw new AuthenticationException(
                "Too many failed attempts. Account is temporarily locked for {$this->lockoutMinutes} minutes.",
                'ACCOUNT_LOCKED'
            );
        }
    }

    public function recordFailure(string $email, string $ip): void
    {
        $this->db->insert('login_attempts', [
            'identifier'   => $email,
            'ip_address'   => $ip,
            'success'      => 0,
            'attempted_at' => date('Y-m-d H:i:s'),
        ]);
    }

    public function recordSuccess(string $email, string $ip): void
    {
        $this->db->insert('login_attempts', [
            'identifier'   => $email,
            'ip_address'   => $ip,
            'success'      => 1,
            'attempted_at' => date('Y-m-d H:i:s'),
        ]);
    }

    public function clearFailures(string $email, string $ip): void
    {
        $this->db->execute(
            "DELETE FROM login_attempts WHERE identifier = ? AND success = 0",
            [$email]
        );
    }
}
