<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Core\Request;
use App\Core\Response;
use App\Domain\Users\RoleService;
use App\Domain\Users\AuthUser;

/**
 * RoleController — Roles and permissions management endpoints.
 */
class RoleController
{
    public function __construct(private readonly RoleService $roleService) {}

    // ── GET /api/v1/roles ─────────────────────────────────────────────────────

    public function index(Request $request): Response
    {
        return Response::json([
            'success' => true,
            'data'    => $this->roleService->listRoles(),
            'meta'    => ['request_id' => $request->getId()],
        ]);
    }

    // ── POST /api/v1/roles ────────────────────────────────────────────────────

    public function store(Request $request): Response
    {
        /** @var AuthUser $actor */
        $actor  = $request->getAttribute('auth_user');
        $result = $this->roleService->createRole($request->input(), $actor->getId());

        return Response::json([
            'success' => true,
            'data'    => $result,
            'meta'    => ['request_id' => $request->getId()],
        ], 201);
    }

    // ── GET /api/v1/roles/{id} ────────────────────────────────────────────────

    public function show(Request $request): Response
    {
        $id = (int) $request->param('id');
        return Response::json([
            'success' => true,
            'data'    => $this->roleService->getRole($id),
            'meta'    => ['request_id' => $request->getId()],
        ]);
    }

    // ── PATCH /api/v1/roles/{id} ──────────────────────────────────────────────

    public function update(Request $request): Response
    {
        // TODO: delegate to RoleService::updateRole()
        return Response::json(['success' => true, 'data' => [], 'meta' => ['request_id' => $request->getId()]]);
    }

    // ── POST /api/v1/roles/{id}/permissions ──────────────────────────────────

    public function assignPermission(Request $request): Response
    {
        $roleId = (int) $request->param('id');
        $permId = (int) $request->input('permission_id');
        /** @var AuthUser $actor */
        $actor  = $request->getAttribute('auth_user');

        $this->roleService->assignPermissionToRole($roleId, $permId, $actor->getId());

        return Response::json([
            'success' => true,
            'data'    => ['message' => 'Permission assigned to role.'],
            'meta'    => ['request_id' => $request->getId()],
        ]);
    }

    // ── DELETE /api/v1/roles/{id}/permissions/{permId} ───────────────────────

    public function revokePermission(Request $request): Response
    {
        $roleId = (int) $request->param('id');
        $permId = (int) $request->param('permId');
        /** @var AuthUser $actor */
        $actor  = $request->getAttribute('auth_user');

        $this->roleService->revokePermissionFromRole($roleId, $permId, $actor->getId());

        return Response::json([
            'success' => true,
            'data'    => ['message' => 'Permission revoked from role.'],
            'meta'    => ['request_id' => $request->getId()],
        ]);
    }

    // ── GET /api/v1/permissions ───────────────────────────────────────────────

    public function permissions(Request $request): Response
    {
        return Response::json([
            'success' => true,
            'data'    => $this->roleService->listPermissions(),
            'meta'    => ['request_id' => $request->getId()],
        ]);
    }
}
