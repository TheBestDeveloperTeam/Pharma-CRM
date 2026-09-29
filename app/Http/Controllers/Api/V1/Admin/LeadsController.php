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
use App\Domain\Leads\LeadService;
use App\Domain\Leads\MobileNormalizer;
use App\Repositories\Contracts\LeadRepositoryInterface;
use App\Policies\SalesLeadPolicy;

/**
 * B1 — authorization is permission keys (leads.view/create/edit/assign) and
 * data scope is the user's `leads` scope (CrmScopePolicy via SalesLeadPolicy).
 * users.role is no longer consulted here.
 */
final class LeadsController
{
    public function __construct(
        private LeadRepositoryInterface $leads,
        private LeadService $leadService,
        private AuthorizationService $authorization,
    ) {}

    public function index(Request $r): Response
    {
        $ctx = TenantContext::get();
        $this->authorization->requirePermission($ctx, 'leads', 'view');
        $franchiseRef = $ctx->franchiseRef;
        $page = (int)$r->query('page', 1);
        $perPage = min((int)$r->query('per_page', 20), 100);

        $filters = [
            'status' => $r->query('status'),
            'search' => $r->query('search'),
        ];

        $scopeParams = [];
        $scopeSql = SalesLeadPolicy::listClause($ctx, $scopeParams);

        $res = $this->leads->list($franchiseRef, $filters, $page, $perPage, null, $scopeSql, $scopeParams);
        return Response::json(200, $res['items'] ?? [], [
            'page'        => $res['page'] ?? $page,
            'per_page'    => $res['per_page'] ?? $perPage,
            'total'       => $res['total'] ?? 0,
            'total_pages' => $res['total_pages'] ?? 1,
        ]);
    }

    public function show(Request $r, ?string $ref = null): Response
    {
        $ref = $ref ?: (string)$r->param('ref');
        $ctx = TenantContext::get();
        $this->authorization->requirePermission($ctx, 'leads', 'view');
        $lead = $this->leads->findByRef($ctx->franchiseRef, $ref);

        if (!$lead) {
            throw new NotFoundException('LEAD_NOT_FOUND', 'Lead not found.');
        }

        if (!SalesLeadPolicy::canView($ctx, $lead)) {
            throw new ForbiddenException('FORBIDDEN_LEAD', 'You do not have permission to view this lead.');
        }

        $activities = $this->leads->getActivities($ctx->franchiseRef, $ref);
        $lead['activities'] = $activities;

        return Response::json(200, $lead);
    }

    public function store(Request $r): Response
    {
        $ctx = TenantContext::get();
        $this->authorization->requirePermission($ctx, 'leads', 'create');
        $clean = Validation::validate($r->all(), [
            'contact_name' => 'required|string',
            'mobile'       => 'required|string',
        ]);

        $data = array_merge($r->all(), [
            'org_ref'        => $ctx->orgRef,
            'franchise_ref'  => $ctx->franchiseRef,
            'created_by_ref' => $ctx->userRef,
        ]);

        // A user who can only see some leads keeps what they create; only an
        // ALL-scope user may assign on create (or leave it to round-robin).
        if (!$ctx->isSuper() && $ctx->scopeFor('leads') !== 'ALL') {
            $data['assigned_user_ref'] = $ctx->userRef;
        }

        $leadRef = $this->leadService->create($data);
        $lead = $this->leads->findByRef($ctx->franchiseRef, $leadRef);

        return Response::json(201, $lead);
    }

    public function update(Request $r, ?string $ref = null): Response
    {
        $ref = $ref ?: (string)$r->param('ref');
        $ctx = TenantContext::get();
        $this->authorization->requirePermission($ctx, 'leads', 'edit');
        $lead = $this->leads->findByRef($ctx->franchiseRef, $ref);

        if (!$lead) {
            throw new NotFoundException('LEAD_NOT_FOUND', 'Lead not found.');
        }

        if (!SalesLeadPolicy::canUpdate($ctx, $lead)) {
            throw new ForbiddenException('FORBIDDEN_LEAD', 'You do not have permission to edit this lead.');
        }

        $data = $r->all();
        $data['updated_by_ref'] = $ctx->userRef;
        if (!empty($data['mobile'])) {
            $data['mobile_norm'] = MobileNormalizer::normalize($data['mobile']);
        }

        $this->leads->update($ctx->franchiseRef, $ref, $data);
        return Response::json(200, $this->leads->findByRef($ctx->franchiseRef, $ref));
    }

    public function status(Request $r, ?string $ref = null): Response
    {
        $ref = $ref ?: (string)$r->param('ref');
        $ctx = TenantContext::get();
        // B1 — previously this endpoint had no authorization at all.
        $this->authorization->requirePermission($ctx, 'leads', 'edit');
        $lead = $this->leads->findByRef($ctx->franchiseRef, $ref);
        if (!$lead) {
            throw new NotFoundException('LEAD_NOT_FOUND', 'Lead not found.');
        }
        if (!SalesLeadPolicy::canUpdate($ctx, $lead)) {
            throw new ForbiddenException('FORBIDDEN_LEAD', 'You do not have permission to edit this lead.');
        }

        $clean = Validation::validate($r->all(), [
            'status' => 'required|string',
        ]);

        $note = $r->input('note');
        $this->leadService->changeStatus($ctx->franchiseRef, $ref, $clean['status'], $ctx->userRef, $note);

        return Response::json(200, $this->leads->findByRef($ctx->franchiseRef, $ref));
    }

    public function assign(Request $r, ?string $ref = null): Response
    {
        $ref = $ref ?: (string)$r->param('ref');
        $ctx = TenantContext::get();
        if (!$ctx->can('leads', 'assign')) {
            throw new ForbiddenException('FORBIDDEN', 'Permission required: leads.assign');
        }

        $clean = Validation::validate($r->all(), [
            'assigned_user_ref' => 'required|string',
        ]);

        $reason = $r->input('reason');
        $this->leadService->reassign($ctx->franchiseRef, $ref, $clean['assigned_user_ref'], $ctx->userRef, $reason);

        return Response::json(200, $this->leads->findByRef($ctx->franchiseRef, $ref));
    }

    /** BE-032: Archive a lead (soft status change). */
    public function archive(Request $r, ?string $ref = null): Response
    {
        $ref = $ref ?: (string)$r->param('ref');
        $ctx = TenantContext::get();
        $this->authorization->requirePermission($ctx, 'leads', 'edit');
        $lead = $this->leads->findByRef($ctx->franchiseRef, $ref);
        if (!$lead) throw new NotFoundException('LEAD_NOT_FOUND', 'Lead not found.');
        if (!SalesLeadPolicy::canUpdate($ctx, $lead)) throw new ForbiddenException('FORBIDDEN_LEAD', 'You do not have permission to archive this lead.');
        if ($lead['status'] === 'ARCHIVED') return Response::json(200, $lead);

        $this->leads->updateStatus($ctx->franchiseRef, $ref, 'ARCHIVED');
        $this->leads->addActivity($ctx->franchiseRef, [
            'lead_ref' => $ref, 'franchise_ref' => $ctx->franchiseRef, 'org_ref' => $ctx->orgRef,
            'actor_ref' => $ctx->userRef, 'activity_type' => 'STATUS_CHANGE', 'description' => 'Lead archived',
        ]);
        return Response::json(200, $this->leads->findByRef($ctx->franchiseRef, $ref));
    }

    /** BE-032: Restore a previously archived lead. */
    public function restore(Request $r, ?string $ref = null): Response
    {
        $ref = $ref ?: (string)$r->param('ref');
        $ctx = TenantContext::get();
        $this->authorization->requirePermission($ctx, 'leads', 'edit');
        $lead = $this->leads->findByRef($ctx->franchiseRef, $ref);
        if (!$lead) throw new NotFoundException('LEAD_NOT_FOUND', 'Lead not found.');
        if (!SalesLeadPolicy::canUpdate($ctx, $lead)) throw new ForbiddenException('FORBIDDEN_LEAD', 'You do not have permission to restore this lead.');
        if ($lead['status'] !== 'ARCHIVED') return Response::json(200, $lead);

        $this->leads->updateStatus($ctx->franchiseRef, $ref, 'NEW');
        $this->leads->addActivity($ctx->franchiseRef, [
            'lead_ref' => $ref, 'franchise_ref' => $ctx->franchiseRef, 'org_ref' => $ctx->orgRef,
            'actor_ref' => $ctx->userRef, 'activity_type' => 'STATUS_CHANGE', 'description' => 'Lead restored from archive',
        ]);
        return Response::json(200, $this->leads->findByRef($ctx->franchiseRef, $ref));
    }
}
