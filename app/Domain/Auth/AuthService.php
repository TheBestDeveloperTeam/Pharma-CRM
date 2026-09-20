<?php

declare(strict_types=1);

namespace App\Domain\Auth;

use App\Core\JwtService;
use App\Core\Exceptions\AuthenticationException;
use App\Core\Exceptions\BusinessRuleException;
use App\Core\Validation;
use App\Domain\Audit\AuditService;
use App\Repositories\Sql\UserRepository;
use App\Repositories\Sql\TokenRepository;

/**
 * AuthService — Login, logout, refresh, token lifecycle.
 */
class AuthService
{
    public function __construct(
        private readonly UserRepository  $users,
        private readonly TokenRepository $tokens,
        private readonly JwtService      $jwt,
        private readonly BruteForceService $bruteForce,
        private readonly AuditService    $audit,
    ) {}

    // ── Login ─────────────────────────────────────────────────────────────────

    public function login(array $credentials, string $ip, string $userAgent): array
    {
        (new Validation($credentials, [
            'email'    => 'required|email',
            'password' => 'required',
        ]))->validate();

        $email = strtolower(trim($credentials['email']));

        // Check brute force lockout
        $this->bruteForce->checkLockout($email, $ip);

        // Load user
        $user = $this->users->findByEmail($email);

        if (!$user || !password_verify($credentials['password'], $user['password_hash'])) {
            $this->bruteForce->recordFailure($email, $ip);
            $this->audit->log('auth.login_failed', 'user', null, null, null, ['email' => $email, 'ip' => $ip]);
            throw new AuthenticationException('Invalid email or password.');
        }

        if ($user['status'] !== 'active') {
            throw new AuthenticationException('Account is inactive. Contact administrator.');
        }

        // Check password expiry
        if ($this->isPasswordExpired($user)) {
            throw new BusinessRuleException('Password has expired. Please reset your password.', 'PASSWORD_EXPIRED');
        }

        // Clear brute force on success
        $this->bruteForce->clearFailures($email, $ip);

        // Load roles and permissions
        $roles       = $this->users->getRoles($user['id']);
        $permissions = $this->users->getPermissions($user['id']);

        $tokenPayload = [
            'sub'   => (string) $user['id'],
            'email' => $user['email'],
            'roles' => $roles,
        ];

        $accessToken  = $this->jwt->issueAccessToken($tokenPayload);
        $refreshToken = $this->jwt->issueRefreshToken($tokenPayload);

        // Store refresh token in DB
        $this->tokens->store($user['id'], $refreshToken, time() + $this->jwt->getRefreshTtl());

        // Update last login
        $this->users->updateLastLogin($user['id'], $ip);

        $this->audit->log('auth.login', 'user', $user['id'], null, null, ['ip' => $ip]);

        return [
            'access_token'  => $accessToken,
            'refresh_token' => $refreshToken,
            'token_type'    => 'Bearer',
            'expires_in'    => $this->jwt->getAccessTtl(),
            'user'          => [
                'id'          => $user['id'],
                'name'        => $user['name'],
                'email'       => $user['email'],
                'roles'       => $roles,
                'permissions' => $permissions,
            ],
        ];
    }

    // ── Logout ────────────────────────────────────────────────────────────────

    public function logout(string $accessToken, int $userId): void
    {
        $this->tokens->revoke($accessToken);
        $this->audit->log('auth.logout', 'user', $userId, null, null, []);
    }

    // ── Refresh ───────────────────────────────────────────────────────────────

    public function refresh(string $refreshToken): array
    {
        try {
            $payload = $this->jwt->verify($refreshToken);
        } catch (\Throwable) {
            throw new AuthenticationException('Invalid or expired refresh token.');
        }

        if (($payload['typ'] ?? '') !== 'refresh') {
            throw new AuthenticationException('Token is not a refresh token.');
        }

        // Check refresh token is still valid in DB
        if (!$this->tokens->isValid($refreshToken)) {
            throw new AuthenticationException('Refresh token has been revoked.');
        }

        $userId = (int) $payload['sub'];
        $user   = $this->users->findById($userId);

        if (!$user || $user['status'] !== 'active') {
            throw new AuthenticationException('User not found or inactive.');
        }

        $roles = $this->users->getRoles($userId);

        $newPayload = [
            'sub'   => (string) $userId,
            'email' => $user['email'],
            'roles' => $roles,
        ];

        $newAccessToken = $this->jwt->issueAccessToken($newPayload);

        return [
            'access_token' => $newAccessToken,
            'token_type'   => 'Bearer',
            'expires_in'   => $this->jwt->getAccessTtl(),
        ];
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function isPasswordExpired(array $user): bool
    {
        $expiryDays = (int) env('PASSWORD_EXPIRY_DAYS', 90);
        if ($expiryDays <= 0) return false;
        if (empty($user['password_changed_at'])) return false;

        $changed = strtotime($user['password_changed_at']);
        return (time() - $changed) > ($expiryDays * 86400);
    }
}
