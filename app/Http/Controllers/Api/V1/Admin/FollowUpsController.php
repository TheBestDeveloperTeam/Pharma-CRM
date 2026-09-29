<?php
declare(strict_types=1);
namespace App\Http\Controllers\Api\V1\Admin;

use App\Core\Request;
use App\Core\Response;
use App\Core\Validation;
use App\Core\TenantContext;
use App\Core\Exceptions\ForbiddenException;
use App\Core\Exceptions\NotFoundException;
use App\Domain\Authorization\AuthorizationService;
use App\Domain\FollowUps\FollowUpService;
use App\Repositories\Contracts\FollowUpRepositoryInterface;
use App\Policies\SalesFollowUpPolicy;

/**
 * B1 — authorization is permission keys (followUps.view/create/complete/
 * reschedule) and data scope is the user's `followUps` scope. users.role is
 * no longer consulted here.
 */
final class FollowUpsController
{
    public function __construct(
        private FollowUpRepositoryInterface $followups,
        private FollowUpService $followUpService,
        private AuthorizationService $authorization,
    ) {}

    public function index(Request $r): Response
    {
        $ctx = TenantContext::get();
        $this->authorization->requirePermission($ctx, 'followUps', 'view');
        $franchiseRef = $ctx->franchiseRef;
        $page = (int)$r->query('page', 1);
        $perPage = min((int)$r->query('per_page', 20), 100);

        $filters = [
            'status'   => $r->query('status'),
            'lead_ref' => $r->query('lead_ref'),
            'overdue'  => $r->query('overdue'),
        ];

        $scopeParams = [];
        $scopeSql = SalesFollowUpPolicy::listClause($ctx, $scopeParams);

        $res = $this->followups->list($franchiseRef, $filters, $page, $perPage, null, $scopeSql, $scopeParams);
        return Response::json(200, $res['items'] ?? [], [
            'page'        => $res['page'] ?? $page,
            'per_page'    => $res['per_page'] ?? $perPage,
            'total'       => $res['total'] ?? 0,
            'total_pages' => $res['total_pages'] ?? 1,
        ]);
    }

    public function store(Request $r): Response
    {
        $ctx = TenantContext::get();
        $this->authorization->requirePermission($ctx, 'followUps', 'create');
        $clean = Validation::validate($r->all(), [
            'activity_type'     => 'required|string',
            'next_action'       => 'required|string',
            'next_follow_up_at' => 'required|string',
        ]);

        // Only an ALL-scope user may schedule a follow-up for someone else.
        $canAssignOthers = $ctx->isSuper() || $ctx->scopeFor('followUps') === 'ALL';

        $data = array_merge($r->all(), [
            'org_ref'           => $ctx->orgRef,
            'franchise_ref'     => $ctx->franchiseRef,
            'assigned_user_ref' => $canAssignOthers ? ($r->input('assigned_user_ref') ?? $ctx->userRef) : $ctx->userRef,
            'created_by_ref'    => $ctx->userRef,
        ]);

        $fuRef = $this->followUpService->create($data);
        return Response::json(201, $this->followups->findByRef($ctx->franchiseRef, $fuRef));
    }

    public function complete(Request $r, ?string $ref = null): Response
    {
        $ref = $ref ?: (string)$r->param('ref');
        $ctx = TenantContext::get();
        $this->authorization->requirePermission($ctx, 'followUps', 'complete');
        $fu = $this->followups->findByRef($ctx->franchiseRef, $ref);

        if (!$fu) {
            throw new NotFoundException('FOLLOWUP_NOT_FOUND', 'Follow-up not found.');
        }

        if (!SalesFollowUpPolicy::canUpdate($ctx, $fu)) {
            throw new ForbiddenException('FORBIDDEN_FOLLOWUP', 'You do not have permission to modify this follow-up.');
        }

        $remark = $r->input('remark');
        $this->followUpService->complete($ctx->franchiseRef, $ref, $remark);

        return Response::json(200, $this->followups->findByRef($ctx->franchiseRef, $ref));
    }

    public function reschedule(Request $r, ?string $ref = null): Response
    {
        $ref = $ref ?: (string)$r->param('ref');
        $ctx = TenantContext::get();
        $this->authorization->requirePermission($ctx, 'followUps', 'reschedule');
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

        return Response::json(200, $this->followups->findByRef($ctx->franchiseRef, $ref));
    }

    /** BE-040: Mark a follow-up as missed (did not happen). */
    public function markMissed(Request $r, ?string $ref = null): Response
    {
        $ref = $ref ?: (string)$r->param('ref');
        $ctx = TenantContext::get();
        $this->authorization->requirePermission($ctx, 'followUps', 'complete');
        $fu = $this->followups->findByRef($ctx->franchiseRef, $ref);
        if (!$fu) throw new NotFoundException('FOLLOWUP_NOT_FOUND', 'Follow-up not found.');
        if (!SalesFollowUpPolicy::canUpdate($ctx, $fu)) throw new ForbiddenException('FORBIDDEN_FOLLOWUP', 'You do not have permission to modify this follow-up.');

        $remark = $r->input('remark', 'Marked as missed');
        $this->followups->update($ctx->franchiseRef, $ref, ['status' => 'MISSED', 'remark' => $remark]);
        return Response::json(200, $this->followups->findByRef($ctx->franchiseRef, $ref));
    }

    /** BE-041: List follow-up history for a specific lead or party. */
    public function history(Request $r): Response
    {
        $ctx = TenantContext::get();
        $this->authorization->requirePermission($ctx, 'followUps', 'view');
        $franchiseRef = $ctx->franchiseRef;

        $leadRef = $r->query('lead_ref');
        $partyRef = $r->query('party_ref');
        $page = (int)$r->query('page', 1);
        $perPage = min((int)$r->query('per_page', 50), 100);

        $filters = ['lead_ref' => $leadRef];
        if ($partyRef) {
            $filters['party_ref'] = $partyRef;
        }

        // Show all statuses for history (PENDING, COMPLETED, MISSED, RESCHEDULED)
        $scopeParams = [];
        $scopeSql = SalesFollowUpPolicy::listClause($ctx, $scopeParams);
        $res = $this->followups->list($franchiseRef, $filters, $page, $perPage, null, $scopeSql, $scopeParams);

        return Response::json(200, $res['items'] ?? [], [
            'page'        => $res['page'] ?? $page,
            'per_page'    => $res['per_page'] ?? $perPage,
            'total'       => $res['total'] ?? 0,
            'total_pages' => $res['total_pages'] ?? 1,
        ]);
    }
}
