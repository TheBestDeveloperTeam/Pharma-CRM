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
}
