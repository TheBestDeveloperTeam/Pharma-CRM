<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Core\Request;
use App\Core\Response;
use App\Core\Exceptions\AuthorizationException;
use App\Domain\Users\UserService;
use App\Domain\Users\AuthUser;

/**
 * UserController — User CRUD and role assignment endpoints.
 */
class UserController
{
    public function __construct(private readonly UserService $userService) {}

    // ── GET /api/v1/users ─────────────────────────────────────────────────────

    public function index(Request $request): Response
    {
        $result = $this->userService->list(
            filters: $request->query(),
            page:    (int) $request->query('page', 1),
            perPage: min((int) $request->query('per_page', 25), 100),
        );

        return Response::json([
            'success' => true,
            'data'    => $result['data'],
            'meta'    => array_merge(['request_id' => $request->getId()], $result['meta']),
        ]);
    }

    // ── POST /api/v1/users ────────────────────────────────────────────────────

    public function store(Request $request): Response
    {
        /** @var AuthUser $actor */
        $actor  = $request->getAttribute('auth_user');
        $result = $this->userService->create($request->input(), $actor->getId());

        return Response::json([
            'success' => true,
            'data'    => $result,
            'meta'    => ['request_id' => $request->getId()],
        ], 201);
    }

    // ── GET /api/v1/users/{id} ────────────────────────────────────────────────

    public function show(Request $request): Response
    {
        $id     = (int) $request->param('id');
        /** @var AuthUser $actor */
        $actor  = $request->getAttribute('auth_user');

        // Users may only view their own profile unless they are admin
        if (!$actor->isAdmin() && $actor->getId() !== $id) {
            throw new AuthorizationException('You can only view your own profile.');
        }

        return Response::json([
            'success' => true,
            'data'    => $this->userService->get($id),
            'meta'    => ['request_id' => $request->getId()],
        ]);
    }

    // ── PATCH /api/v1/users/{id} ──────────────────────────────────────────────

    public function update(Request $request): Response
    {
        $id    = (int) $request->param('id');
        /** @var AuthUser $actor */
        $actor = $request->getAttribute('auth_user');

        if (!$actor->isAdmin() && $actor->getId() !== $id) {
            throw new AuthorizationException('You can only update your own profile.');
        }

        $result = $this->userService->update($id, $request->input(), $actor->getId());

        return Response::json([
            'success' => true,
            'data'    => $result,
            'meta'    => ['request_id' => $request->getId()],
        ]);
    }

    // ── POST /api/v1/users/{id}/activate ─────────────────────────────────────

    public function activate(Request $request): Response
    {
        $id    = (int) $request->param('id');
        /** @var AuthUser $actor */
        $actor = $request->getAttribute('auth_user');
        $this->userService->activate($id, $actor->getId());

        return Response::json([
            'success' => true,
            'data'    => ['message' => 'User activated.'],
            'meta'    => ['request_id' => $request->getId()],
        ]);
    }

    // ── POST /api/v1/users/{id}/deactivate ───────────────────────────────────

    public function deactivate(Request $request): Response
    {
        $id    = (int) $request->param('id');
        /** @var AuthUser $actor */
        $actor = $request->getAttribute('auth_user');
        $this->userService->deactivate($id, $actor->getId());

        return Response::json([
            'success' => true,
            'data'    => ['message' => 'User deactivated.'],
            'meta'    => ['request_id' => $request->getId()],
        ]);
    }

    // ── GET /api/v1/users/{id}/roles ─────────────────────────────────────────

    public function roles(Request $request): Response
    {
        $id     = (int) $request->param('id');
        $detail = $this->userService->get($id);

        return Response::json([
            'success' => true,
            'data'    => $detail['roles'] ?? [],
            'meta'    => ['request_id' => $request->getId()],
        ]);
    }

    // ── POST /api/v1/users/{id}/roles ────────────────────────────────────────

    public function assignRole(Request $request): Response
    {
        // TODO: delegate to UserService::assignRole() — Sprint 1.3
        return Response::json(['success' => true, 'data' => ['message' => 'Role assigned.'], 'meta' => ['request_id' => $request->getId()]]);
    }

    // ── DELETE /api/v1/users/{id}/roles/{roleId} ──────────────────────────────

    public function revokeRole(Request $request): Response
    {
        // TODO: delegate to UserService::revokeRole() — Sprint 1.3
        return Response::json(['success' => true, 'data' => ['message' => 'Role revoked.'], 'meta' => ['request_id' => $request->getId()]]);
    }
}
