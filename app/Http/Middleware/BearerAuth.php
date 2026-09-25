<?php
declare(strict_types=1);
namespace App\Http\Middleware;

use App\Core\{Request, Response, Container, FileCache};
use App\Core\Security\Jwt;
use App\Core\Exceptions\UnauthorizedException;
use App\Domain\Authorization\AuthorizationService;

final class BearerAuth
{
    private const EXCLUDED_PREFIXES = [
        '/api/v1/oauth/',
        '/api/v1/health',
        '/api/v1/ready',
        '/api/v1/webhooks/',
        '/api/v1/onboarding/',
        '/api/v1/geo/',
        '/geo/',
        '/super/login',
        '/admin/login',
        '/sales/login',
        '/portal/login',
        '/health',
        '/ready',
    ];

    public function __construct(
        private Jwt $jwt,
        private \PDO $pdo,
        private FileCache $cache,
        private AuthorizationService $authorization
    ) {}

    public function __invoke(Request $r, callable $next): Response
    {
        // Check if route is public
        foreach (self::EXCLUDED_PREFIXES as $prefix) {
            if (str_starts_with($r->path, $prefix) || $r->path === rtrim($prefix, '/')) {
                return $next($r);
            }
        }

        // Web shell views can pass through without API bearer token (client JS manages auth)
        if (!str_starts_with($r->path, '/api/')) {
            return $next($r);
        }

        $token = $r->bearerToken();
        if ($token === '') {
            throw new UnauthorizedException('TOKEN_MISSING', 'Bearer authorization token required.');
        }

        $surface = $r->surface(); // super | admin | sales | portal | api
        $audHeader = $r->header('x-surface');
        $expectedAud = $audHeader !== '' ? $audHeader : (($surface === 'api') ? null : $surface);

        try {
            $payload = $this->jwt->verify($token, $expectedAud);
        } catch (\App\Core\Exceptions\UnauthorizedException $e) {
            // Log security event for audit if token verification failed
            if (Container::getInstance()->has(\App\Domain\Audit\AuditService::class)) {
                /** @var \App\Domain\Audit\AuditService $audit */
                $audit = Container::getInstance()->make(\App\Domain\Audit\AuditService::class);
                $dummyCtx = new \App\Core\TenantContext(
                    orgRef: 'SYSTEM',
                    franchiseRef: null,
                    userRef: 'GUEST',
                    role: 'GUEST',
                    scope: 'PLATFORM',
                    partyRef: null,
                    requestId: \App\Core\RequestId::current()
                );
                $audit->log(
                    ctx: $dummyCtx,
                    category: 'SECURITY',
                    action: 'auth.token_rejected',
                    entityType: 'jwt',
                    entityRef: null,
                    reason: $e->getMessage()
                );
            }
            throw $e;
        }

        // Verify session not revoked
        $sessionId = $payload['sid'] ?? '';
        if ($sessionId !== '') {
            $stmtSession = $this->pdo->prepare("SELECT revoked_at FROM user_sessions WHERE session_ref = :s LIMIT 1");
            $stmtSession->execute([':s' => $sessionId]);
            $revokedAt = $stmtSession->fetchColumn();
            if ($revokedAt !== false && $revokedAt !== null) {
                throw new UnauthorizedException('SESSION_REVOKED', 'Session has been revoked.');
            }
        }

        // Load and check active status from DB on every request. A short-lived
        // cache would allow a deactivated user to keep calling protected APIs.
        $userRef = $payload['sub'];
        $stmtUser = $this->pdo->prepare("SELECT user_ref, org_ref, franchise_ref, role, party_ref, status FROM users WHERE user_ref = :u LIMIT 1");
        $stmtUser->execute([':u' => $userRef]);
        $user = $stmtUser->fetch(\PDO::FETCH_ASSOC) ?: null;

        if (!$user || $user['status'] !== 'ACTIVE') {
            throw new UnauthorizedException('USER_INACTIVE', 'User account is not active.');
        }

        $effective = $this->authorization->effectiveForUser($user['user_ref'], $user['franchise_ref']);

        // Build TenantContext. The legacy role is retained only for surface
        // compatibility; permissions/scopes come from normalized auth_* data.
        $tenantCtx = new \App\Core\TenantContext(
            orgRef: $user['org_ref'],
            franchiseRef: $user['franchise_ref'],
            userRef: $user['user_ref'],
            role: $user['role'],
            scope: $user['role'] === 'SUPER_ADMIN' ? 'PLATFORM' : 'FRANCHISE',
            partyRef: $user['party_ref'],
            requestId: \App\Core\RequestId::current(),
            impersonatorRef: $payload['imp'] ?? null,
            roles: array_map(static fn(array $role): string => (string)$role['role_slug'], $effective['roles']),
            permissions: $effective['permissions'],
            scopes: $effective['scopes'],
            teamUserRefs: $effective['team_user_refs'],
            territoryRefs: $effective['territory_refs']
        );

        Container::getInstance()->instance(\App\Core\TenantContext::class, $tenantCtx);

        return $next($r);
    }
}
