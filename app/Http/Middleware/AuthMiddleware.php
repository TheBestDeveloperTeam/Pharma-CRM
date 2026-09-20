<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Core\Request;
use App\Core\Response;
use App\Core\JwtService;
use App\Core\Exceptions\AuthenticationException;
use App\Repositories\Sql\UserRepository;
use App\Repositories\Sql\TokenRepository;

/**
 * AuthMiddleware — Verifies Bearer JWT on every protected route.
 *
 * On success: injects 'auth_user' attribute into Request.
 * On failure: throws AuthenticationException (→ 401).
 */
class AuthMiddleware
{
    public function __construct(
        private readonly JwtService      $jwt,
        private readonly UserRepository  $users,
        private readonly TokenRepository $tokens,
    ) {}

    public function handle(Request $request, callable $next): Response
    {
        $token = $request->bearerToken();

        if (!$token) {
            throw new AuthenticationException('Bearer token is required.');
        }

        // Verify JWT signature and expiry
        try {
            $payload = $this->jwt->verify($token);
        } catch (\Throwable $e) {
            throw new AuthenticationException('Invalid or expired token.');
        }

        $userId = $payload['sub'] ?? null;
        if (!$userId) {
            throw new AuthenticationException('Invalid token payload.');
        }

        // Access tokens are stateless, verified by signature above.

        // Load user
        $user = $this->users->findById((int) $userId);
        if (!$user || $user['status'] !== 'active') {
            throw new AuthenticationException('User account is inactive or not found.');
        }

        // Inject user into request for downstream use
        $request->setAttribute('auth_user', new \App\Domain\Users\AuthUser($user));
        $request->setAttribute('auth_token_payload', $payload);

        return $next($request);
    }
}
