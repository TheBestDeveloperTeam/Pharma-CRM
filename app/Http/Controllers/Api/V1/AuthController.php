<?php
declare(strict_types=1);
namespace App\Http\Controllers\Api\V1;

use App\Core\{Request, Response, Container, Validation, TenantContext};
use App\Core\Security\{PasswordHasher, TokenService, RateLimiter};
use App\Core\Exceptions\{UnauthorizedException, ValidationException};

final class AuthController
{
    public function __construct(
        private \PDO $pdo,
        private PasswordHasher $hasher,
        private TokenService $tokens,
        private RateLimiter $limiter,
    ) {}

    /**
     * POST /api/v1/oauth/token
     * Grant types: 'password' | 'refresh_token'
     */
    public function token(Request $r): Response
    {
        $body = $r->all();
        $grantType = $body['grant_type'] ?? '';

        if ($grantType === 'password') {
            return $this->handlePasswordGrant($r);
        }

        if ($grantType === 'refresh_token') {
            return $this->handleRefreshTokenGrant($r);
        }

        throw new ValidationException('INVALID_GRANT_TYPE', 'Supported grant types: password, refresh_token');
    }

    private function handlePasswordGrant(Request $r): Response
    {
        $clean = Validation::validate($r->all(), [
            'client_id' => 'required|string',
            'email'     => 'required|email',
            'password'  => 'required|string',
        ]);

        $clientId = $clean['client_id'];
        $email    = strtolower($clean['email']);
        $password = $clean['password'];
        $frnCode  = trim((string)$r->input('franchise_code', ''));

        // 1. Verify client_id in oauth_clients
        $stmtClient = $this->pdo->prepare("SELECT * FROM oauth_clients WHERE client_id = :c AND status = 'ACTIVE' LIMIT 1");
        $stmtClient->execute([':c' => $clientId]);
        $client = $stmtClient->fetch(\PDO::FETCH_ASSOC);

        if (!$client) {
            throw new UnauthorizedException('INVALID_CLIENT', 'Client ID not registered.');
        }

        $surface = $client['surface'];
        $allowedRoles = explode(',', $client['allowed_roles']);

        // Determine tenant_key: PLATFORM for super, or franchise_ref resolved via franchise_code
        $franchiseRef = null;
        if ($surface === 'super') {
            $tenantKey = 'PLATFORM';
        } else {
            if ($frnCode === '') {
                throw new ValidationException('FRANCHISE_CODE_REQUIRED', 'Franchise code is required for this surface.');
            }
            $stmtFrn = $this->pdo->prepare("SELECT franchise_ref FROM franchises WHERE franchise_code = :code AND status = 'ACTIVE' LIMIT 1");
            $stmtFrn->execute([':code' => $frnCode]);
            $franchiseRef = $stmtFrn->fetchColumn();
            if (!$franchiseRef) {
                // Timing equalization + generic failure
                $this->hasher->dummyVerify();
                throw new UnauthorizedException('INVALID_CREDENTIALS', 'Invalid credentials.');
            }
            $tenantKey = $franchiseRef;
        }

        // 2. Find user
        $stmtUser = $this->pdo->prepare("SELECT * FROM users WHERE email = :e AND tenant_key = :tk LIMIT 1");
        $stmtUser->execute([':e' => $email, ':tk' => $tenantKey]);
        $user = $stmtUser->fetch(\PDO::FETCH_ASSOC);

        if (!$user) {
            $this->limiter->hit('login-ip', $r->clientIp(), 20, 15);
            $this->limiter->hit('login-user', $email . ':' . $tenantKey, 5, 15);
            $this->hasher->dummyVerify();
            throw new UnauthorizedException('INVALID_CREDENTIALS', 'Invalid credentials.');
        }

        // Verify role allowed for surface
        if (!in_array($user['role'], $allowedRoles, true)) {
            $this->limiter->hit('login-ip', $r->clientIp(), 20, 15);
            $this->limiter->hit('login-user', $email . ':' . $tenantKey, 5, 15);
            $this->hasher->dummyVerify();
            throw new UnauthorizedException('INVALID_CREDENTIALS', 'Invalid credentials.');
        }

        // Check password
        if (!$this->hasher->verify($password, $user['password_hash'])) {
            $this->limiter->hit('login-ip', $r->clientIp(), 20, 15);
            $this->limiter->hit('login-user', $email . ':' . $tenantKey, 5, 15);
            $this->pdo->prepare("UPDATE users SET failed_login_count = failed_login_count + 1 WHERE id = :id")->execute([':id' => $user['id']]);
            throw new UnauthorizedException('INVALID_CREDENTIALS', 'Invalid credentials.');
        }

        if ($user['status'] !== 'ACTIVE') {
            throw new UnauthorizedException('ACCOUNT_INACTIVE', 'Account is suspended or locked.');
        }

        // Reset failed login count
        $this->pdo->prepare("UPDATE users SET failed_login_count = 0, last_login_at = NOW() WHERE id = :id")->execute([':id' => $user['id']]);
        $this->limiter->clear('login-user', $email . ':' . $tenantKey);

        $tokens = $this->tokens->issue(
            user: $user,
            clientId: $clientId,
            surface: $surface,
            ipAddress: $r->clientIp(),
            userAgent: $r->header('user-agent')
        );

        return Response::json(200, $tokens);
    }

    private function handleRefreshTokenGrant(Request $r): Response
    {
        $clean = Validation::validate($r->all(), [
            'refresh_token' => 'required|string',
        ]);

        $surface = $r->surface();
        if ($surface === 'api') {
            $surface = $r->header('x-surface') ?: 'admin';
        }

        $tokens = $this->tokens->refresh(
            rawRt: $clean['refresh_token'],
            surface: $surface,
            ipAddress: $r->clientIp(),
            userAgent: $r->header('user-agent')
        );

        return Response::json(200, $tokens);
    }

    /**
     * GET /api/v1/auth/me
     */
    public function me(Request $r): Response
    {
        /** @var TenantContext $ctx */
        $ctx = Container::getInstance()->make(TenantContext::class);

        $stmt = $this->pdo->prepare("SELECT user_ref, full_name, email, role, status FROM users WHERE user_ref = :u LIMIT 1");
        $stmt->execute([':u' => $ctx->userRef]);
        $user = $stmt->fetch(\PDO::FETCH_ASSOC);

        return Response::json(200, [
            'user_ref'         => $ctx->userRef,
            'name'             => $user['full_name'] ?? '',
            'email'            => $user['email'] ?? '',
            'status'           => $user['status'] ?? 'INACTIVE',
            'role'             => $ctx->role,
            'roles'            => $ctx->roles,
            'permissions'      => $ctx->permissions,
            'scopes'           => $ctx->scopes,
            'scope'            => $ctx->scope,
            'org_ref'          => $ctx->orgRef,
            'franchise_ref'    => $ctx->franchiseRef,
            'party_ref'        => $ctx->partyRef,
            'impersonator_ref' => $ctx->impersonatorRef,
        ]);
    }

    /**
     * POST /api/v1/oauth/revoke
     */
    public function revoke(Request $r): Response
    {
        $token = $r->bearerToken();
        if ($token !== '') {
            $parts = explode('.', $token);
            if (count($parts) === 3) {
                $payload = json_decode(base64_decode(strtr($parts[1], '-_', '+/')), true);
                if (is_array($payload) && !empty($payload['sid'])) {
                    $this->tokens->revokeSession($payload['sid']);
                }
            }
        }

        return Response::json(200, ['revoked' => true]);
    }

    /**
     * GET /api/v1/auth/effective-permissions
     * ROL-005: Effective permissions and scopes for the current user session
     */
    public function effectivePermissions(Request $r): Response
    {
        /** @var TenantContext $ctx */
        $ctx = Container::getInstance()->make(TenantContext::class);
        return Response::json(200, [
            'user_ref'    => $ctx->userRef,
            'role'        => $ctx->role,
            'roles'       => $ctx->roles,
            'permissions' => $ctx->permissions,
            'scopes'      => $ctx->scopes,
            'scope'       => $ctx->scope,
            'is_super'    => $ctx->isSuper(),
        ]);
    }

    /**
     * POST /api/v1/auth/change-password
     * UTL-005: Change password for currently authenticated user
     */
    public function changePassword(Request $r): Response
    {
        /** @var TenantContext $ctx */
        $ctx = Container::getInstance()->make(TenantContext::class);

        $clean = Validation::validate($r->all(), [
            'old_password' => 'required|string',
            'new_password' => 'required|string|min:8',
        ]);

        $stmt = $this->pdo->prepare("SELECT id, user_ref, password_hash FROM users WHERE user_ref = :u LIMIT 1");
        $stmt->execute([':u' => $ctx->userRef]);
        $user = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$user) {
            throw new UnauthorizedException('USER_NOT_FOUND', 'User record not found.');
        }

        if (!$this->hasher->verify($clean['old_password'], $user['password_hash'])) {
            throw new ValidationException('INVALID_PASSWORD', 'The current password provided is incorrect.');
        }

        $newHash = $this->hasher->hash($clean['new_password']);
        $updateStmt = $this->pdo->prepare("UPDATE users SET password_hash = :hash, must_change_password = 0, updated_at = NOW() WHERE user_ref = :u");
        $updateStmt->execute([
            ':hash' => $newHash,
            ':u'    => $ctx->userRef,
        ]);

        return Response::json(200, [
            'message' => 'Password updated successfully.',
            'status'  => 'SUCCESS',
        ]);
    }

    /**
     * POST /api/v1/auth/refresh-token
     * UTL-006: Dedicated refresh token endpoint
     */
    public function refreshToken(Request $r): Response
    {
        return $this->handleRefreshTokenGrant($r);
    }

    /**
     * GET /api/v1/auth/sessions
     * UTL-007: List active sessions for the current user
     */
    public function sessions(Request $r): Response
    {
        /** @var TenantContext $ctx */
        $ctx = Container::getInstance()->make(TenantContext::class);

        $stmt = $this->pdo->prepare(
            "SELECT session_ref, client_id, ip_address, user_agent, issued_at, abs_expires_at 
             FROM user_sessions 
             WHERE user_ref = :u AND revoked_at IS NULL AND abs_expires_at > NOW() 
             ORDER BY issued_at DESC"
        );
        $stmt->execute([':u' => $ctx->userRef]);
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];

        return Response::json(200, [
            'sessions' => $rows,
            'total'    => count($rows),
        ]);
    }

    /**
     * POST /api/v1/auth/logout-all
     * UTL-008: Revoke all active sessions for current user
     */
    public function logoutAll(Request $r): Response
    {
        /** @var TenantContext $ctx */
        $ctx = Container::getInstance()->make(TenantContext::class);

        $stmt1 = $this->pdo->prepare(
            "UPDATE user_sessions SET revoked_at = NOW(), revoke_reason = 'LOGOUT_ALL' 
             WHERE user_ref = :u AND revoked_at IS NULL"
        );
        $stmt1->execute([':u' => $ctx->userRef]);

        $stmt2 = $this->pdo->prepare(
            "UPDATE oauth_refresh_tokens SET status = 'REVOKED' 
             WHERE user_ref = :u AND status = 'ACTIVE'"
        );
        $stmt2->execute([':u' => $ctx->userRef]);

        return Response::json(200, [
            'revoked_all' => true,
            'message'     => 'All active sessions have been terminated.',
        ]);
    }
}

