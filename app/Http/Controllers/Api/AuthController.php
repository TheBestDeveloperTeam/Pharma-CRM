<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Core\Request;
use App\Core\Response;
use App\Domain\Auth\AuthService;
use App\Domain\Auth\PasswordResetService;
use App\Domain\Users\UserService;
use App\Domain\Users\AuthUser;

/**
 * AuthController — Login, logout, refresh, me, forgot/reset password.
 */
class AuthController
{
    public function __construct(
        private readonly AuthService          $auth,
        private readonly PasswordResetService $passwordReset,
        private readonly UserService          $users,
    ) {}

    // ── POST /api/v1/auth/login ───────────────────────────────────────────────

    public function login(Request $request): Response
    {
        $result = $this->auth->login(
            $request->input(),
            $request->getIp(),
            $request->getUserAgent()
        );

        return Response::json([
            'success' => true,
            'data'    => $result,
            'meta'    => ['request_id' => $request->getId()],
        ]);
    }

    // ── POST /api/v1/auth/logout ──────────────────────────────────────────────

    public function logout(Request $request): Response
    {
        /** @var AuthUser $user */
        $user  = $request->getAttribute('auth_user');
        $token = $request->bearerToken();

        $this->auth->logout($token, $user->getId());

        return Response::json([
            'success' => true,
            'data'    => ['message' => 'Logged out successfully.'],
            'meta'    => ['request_id' => $request->getId()],
        ]);
    }

    // ── POST /api/v1/auth/refresh ─────────────────────────────────────────────

    public function refresh(Request $request): Response
    {
        $refreshToken = $request->input('refresh_token') ?? $request->bearerToken();

        if (!$refreshToken) {
            return Response::json([
                'success' => false,
                'error'   => ['code' => 'MISSING_REFRESH_TOKEN', 'message' => 'refresh_token is required.'],
                'meta'    => ['request_id' => $request->getId()],
            ], 400);
        }

        $result = $this->auth->refresh($refreshToken);

        return Response::json([
            'success' => true,
            'data'    => $result,
            'meta'    => ['request_id' => $request->getId()],
        ]);
    }

    // ── GET /api/v1/auth/me ───────────────────────────────────────────────────

    public function me(Request $request): Response
    {
        /** @var AuthUser $user */
        $user   = $request->getAttribute('auth_user');
        $detail = $this->users->get($user->getId());

        return Response::json([
            'success' => true,
            'data'    => $detail,
            'meta'    => ['request_id' => $request->getId()],
        ]);
    }

    // ── POST /api/v1/auth/forgot-password ────────────────────────────────────

    public function forgotPassword(Request $request): Response
    {
        $email = $request->input('email', '');
        $this->passwordReset->generateResetToken((string) $email);

        // Always return 200 to prevent user enumeration
        return Response::json([
            'success' => true,
            'data'    => ['message' => 'If that email is registered, a reset link has been sent.'],
            'meta'    => ['request_id' => $request->getId()],
        ]);
    }

    // ── POST /api/v1/auth/reset-password ─────────────────────────────────────

    public function resetPassword(Request $request): Response
    {
        $this->passwordReset->resetPassword(
            $request->input('token', ''),
            $request->input('password', ''),
            $request->input('password_confirmation', '')
        );

        return Response::json([
            'success' => true,
            'data'    => ['message' => 'Password has been reset successfully.'],
            'meta'    => ['request_id' => $request->getId()],
        ]);
    }
}
