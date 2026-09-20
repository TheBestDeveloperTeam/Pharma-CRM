<?php

declare(strict_types=1);

namespace App\Domain\Auth;

use App\Core\Database;
use App\Core\Exceptions\BusinessRuleException;
use App\Core\Exceptions\NotFoundException;
use App\Core\Validation;
use App\Domain\Audit\AuditService;
use App\Repositories\Sql\UserRepository;

/**
 * PasswordResetService — Forgot password flow.
 */
class PasswordResetService
{
    public function __construct(
        private readonly Database       $db,
        private readonly UserRepository $users,
        private readonly AuditService   $audit,
    ) {}

    // ── Forgot Password (generate token) ─────────────────────────────────────

    public function generateResetToken(string $email): string
    {
        $user = $this->users->findByEmail(strtolower(trim($email)));

        // Always return success to prevent user enumeration
        if (!$user) {
            return 'ok';
        }

        // Revoke any existing tokens for this user
        $this->db->execute(
            "DELETE FROM password_resets WHERE user_id = ?",
            [$user['id']]
        );

        $token     = bin2hex(random_bytes(32));
        $tokenHash = hash('sha256', $token);
        $expiresAt = date('Y-m-d H:i:s', time() + 3600); // 1 hour

        $this->db->insert('password_resets', [
            'user_id'    => $user['id'],
            'token_hash' => $tokenHash,
            'expires_at' => $expiresAt,
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        $this->audit->log('auth.password_reset_requested', 'user', $user['id'], null, null, []);

        // In a real system, queue an email notification here
        // For now, return the token (in production, NEVER return this in the response)
        return $token;
    }

    // ── Reset Password ────────────────────────────────────────────────────────

    public function resetPassword(string $token, string $password, string $passwordConfirmation): void
    {
        (new Validation(
            ['password' => $password, 'password_confirmation' => $passwordConfirmation],
            [
                'password'              => 'required|minLength:' . env('PASSWORD_MIN_LENGTH', 8) . '|confirmed',
                'password_confirmation' => 'required',
            ]
        ))->validate();

        $this->validatePasswordPolicy($password);

        $tokenHash = hash('sha256', $token);
        $row = $this->db->fetchOne(
            "SELECT * FROM password_resets WHERE token_hash = ? AND expires_at > NOW()",
            [$tokenHash]
        );

        if (!$row) {
            throw new BusinessRuleException('Invalid or expired password reset token.', 'RESET_TOKEN_INVALID');
        }

        $userId = (int) $row['user_id'];

        // Update password
        $this->db->update('users', [
            'password_hash'       => password_hash($password, PASSWORD_ARGON2ID),
            'password_changed_at' => date('Y-m-d H:i:s'),
            'updated_at'          => date('Y-m-d H:i:s'),
        ], ['id' => $userId]);

        // Consume the token
        $this->db->execute("DELETE FROM password_resets WHERE user_id = ?", [$userId]);

        $this->audit->log('auth.password_reset', 'user', $userId, null, null, []);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function validatePasswordPolicy(string $password): void
    {
        $errors = [];

        $min = (int) env('PASSWORD_MIN_LENGTH', 8);
        if (mb_strlen($password) < $min) {
            $errors[] = "Password must be at least $min characters.";
        }

        if (env('PASSWORD_REQUIRE_UPPERCASE', 'true') === 'true' && !preg_match('/[A-Z]/', $password)) {
            $errors[] = 'Password must contain at least one uppercase letter.';
        }

        if (env('PASSWORD_REQUIRE_LOWERCASE', 'true') === 'true' && !preg_match('/[a-z]/', $password)) {
            $errors[] = 'Password must contain at least one lowercase letter.';
        }

        if (env('PASSWORD_REQUIRE_DIGIT', 'true') === 'true' && !preg_match('/[0-9]/', $password)) {
            $errors[] = 'Password must contain at least one digit.';
        }

        if (!empty($errors)) {
            throw new \App\Core\Exceptions\ValidationException(['password' => $errors]);
        }
    }
}
