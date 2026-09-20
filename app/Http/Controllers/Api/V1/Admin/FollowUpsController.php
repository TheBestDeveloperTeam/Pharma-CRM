<?php
declare(strict_types=1);
namespace App\Http\Controllers\Api\V1\Admin;

use App\Core\Request;
use App\Core\Response;
use App\Core\Validation;
use App\Core\TenantContext;
use App\Core\Exceptions\ForbiddenException;
use App\Core\Exceptions\NotFoundException;
use App\Domain\FollowUps\FollowUpService;
use App\Repositories\Contracts\FollowUpRepositoryInterface;
use App\Policies\SalesFollowUpPolicy;

final class FollowUpsController
{
    public function __construct(
        private FollowUpRepositoryInterface $followups,
        private FollowUpService $followUpService,
    ) {}

    public function index(Request $r): Response
    {
        $ctx = TenantContext::get();
        $franchiseRef = $ctx->franchiseRef;
        $page = (int)$r->query('page', 1);
        $perPage = min((int)$r->query('per_page', 20), 100);

        $assignedUser = ($ctx->role === 'SALES') ? $ctx->userRef : null;

        $filters = [
            'status'   => $r->query('status'),
            'lead_ref' => $r->query('lead_ref'),
            'overdue'  => $r->query('overdue'),
        ];

        $res = $this->followups->list($franchiseRef, $filters, $page, $perPage, $assignedUser);
        return Response::json(['data' => $res]);
    }

    public function store(Request $r): Response
    {
        $ctx = TenantContext::get();
        $clean = Validation::validate($r->all(), [
            'activity_type'     => 'required|string',
            'next_action'       => 'required|string',
            'next_follow_up_at' => 'required|string',
        ]);

        $data = array_merge($r->all(), [
            'org_ref'           => $ctx->orgRef,
            'franchise_ref'     => $ctx->franchiseRef,
            'assigned_user_ref' => ($ctx->role === 'SALES') ? $ctx->userRef : ($r->input('assigned_user_ref') ?? $ctx->userRef),
            'created_by_ref'    => $ctx->userRef,
        ]);

        $fuRef = $this->followUpService->create($data);
        return Response::json(['data' => $this->followups->findByRef($ctx->franchiseRef, $fuRef)], 201);
    }

    public function complete(Request $r, string $ref): Response
    {
        $ctx = TenantContext::get();
        $fu = $this->followups->findByRef($ctx->franchiseRef, $ref);

        if (!$fu) {
            throw new NotFoundException('FOLLOWUP_NOT_FOUND', 'Follow-up not found.');
        }

        if (!SalesFollowUpPolicy::canUpdate($ctx, $fu)) {
            throw new ForbiddenException('FORBIDDEN_FOLLOWUP', 'You do not have permission to modify this follow-up.');
        }

        $remark = $r->input('remark');
        $this->followUpService->complete($ctx->franchiseRef, $ref, $remark);

        return Response::json(['data' => $this->followups->findByRef($ctx->franchiseRef, $ref)]);
    }

    public function reschedule(Request $r, string $ref): Response
    {
        $ctx = TenantContext::get();
        $fu = $this->followups->findByRef($ctx->franchiseRef, $ref);

        if (!$fu) {
            throw new NotFoundException('FOLLOWUP_NOT_FOUND', 'Follow-up not found.');
        }

        if (!SalesFollowUpPolicy::canUpdate($ctx, $fu)) {
            throw new ForbiddenException('FORBIDDEN_FOLLOWUP', 'You do not have permission to reschedule this follow-up.');
        }

        $clean = Validation::validate($r->all(), [
            'next_follow_up_at' => 'required|string',
        ]);

        $remark = $r->input('remark');
        $this->followUpService->reschedule($ctx->franchiseRef, $ref, $clean['next_follow_up_at'], $remark);

        return Response::json(['data' => $this->followups->findByRef($ctx->franchiseRef, $ref)]);
    }
}
