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

    // ── LED-001: Convert lead to party ───────────────────────────

    /** Convert a QUALIFIED lead to a party (franchise partner). */
    public function convert(Request $r, ?string $ref = null): Response
    {
        $ref = $ref ?: (string)$r->param('ref');
        $ctx = TenantContext::get();
        $this->authorization->requirePermission($ctx, 'leads', 'edit');
        $lead = $this->leads->findByRef($ctx->franchiseRef, $ref);
        if (!$lead) throw new NotFoundException('LEAD_NOT_FOUND', 'Lead not found.');
        if (!SalesLeadPolicy::canUpdate($ctx, $lead)) throw new ForbiddenException('FORBIDDEN_LEAD', 'You do not have permission to convert this lead.');

        // Transition via state machine: only QUALIFIED → CONVERTED is allowed
        $this->leadService->changeStatus($ctx->franchiseRef, $ref, 'CONVERTED', $ctx->userRef, 'Converted to party');

        // Create party from lead data
        $partyService = \App\Core\Container::getInstance()->make(\App\Domain\Parties\PartyService::class);
        $partyData = [
            'org_ref'        => $ctx->orgRef,
            'franchise_ref'  => $ctx->franchiseRef,
            'party_name'     => $lead['contact_name'] ?? $lead['firm_name'] ?? $lead['contact_name'],
            'contact_person' => $lead['contact_name'] ?? '',
            'mobile'         => $lead['mobile'] ?? '',
            'email'          => $lead['email'] ?? '',
            'gstin'          => $lead['gstin'] ?? null,
            'state_ref'      => $lead['state_ref'] ?? null,
            'district_ref'   => $lead['district_ref'] ?? null,
            'city_ref'       => $lead['city_ref'] ?? null,
            'pincode'        => $lead['pincode'] ?? null,
            'billing_address'  => $lead['address'] ?? '',
            'assigned_user_ref' => $lead['assigned_user_ref'] ?? null,
            'created_by_ref' => $ctx->userRef,
            'party_type'     => $r->input('party_type', 'DISTRIBUTOR'),
            'source_lead_ref' => $ref,
        ];
        $partyRef = $partyService->create($partyData);

        // Update lead with converted party ref
        $this->leads->updateStatus($ctx->franchiseRef, $ref, 'CONVERTED', $partyRef);

        $this->leads->addActivity($ctx->franchiseRef, [
            'activity_ref' => \App\Core\RefGenerator::generate('ACT'),
            'org_ref'      => $ctx->orgRef,
            'lead_ref'     => $ref,
            'user_ref'     => $ctx->userRef,
            'activity_type' => 'CONVERTED',
            'activity_note' => "Converted to party {$partyRef}",
        ]);

        return Response::json(200, [
            'lead'      => $this->leads->findByRef($ctx->franchiseRef, $ref),
            'party_ref' => $partyRef,
        ]);
    }

    // ── LED-002/003: Lead remarks ────────────────────────────────

    /** List all remarks for a lead. */
    public function remarks(Request $r, ?string $ref = null): Response
    {
        $ref = $ref ?: (string)$r->param('ref');
        $ctx = TenantContext::get();
        $this->authorization->requirePermission($ctx, 'leads', 'view');
        $lead = $this->leads->findByRef($ctx->franchiseRef, $ref);
        if (!$lead) throw new NotFoundException('LEAD_NOT_FOUND', 'Lead not found.');
        if (!SalesLeadPolicy::canView($ctx, $lead)) throw new ForbiddenException('FORBIDDEN_LEAD', 'Forbidden.');

        // Remarks are stored as activities of type REMARK
        $activities = $this->leads->getActivities($ctx->franchiseRef, $ref);
        $remarks = array_values(array_filter($activities, fn($a) => ($a['activity_type'] ?? '') === 'REMARK'));
        return Response::json(200, $remarks);
    }

    /** Add a remark to a lead. */
    public function addRemark(Request $r, ?string $ref = null): Response
    {
        $ref = $ref ?: (string)$r->param('ref');
        $ctx = TenantContext::get();
        $this->authorization->requirePermission($ctx, 'leads', 'edit');
        $lead = $this->leads->findByRef($ctx->franchiseRef, $ref);
        if (!$lead) throw new NotFoundException('LEAD_NOT_FOUND', 'Lead not found.');
        if (!SalesLeadPolicy::canUpdate($ctx, $lead)) throw new ForbiddenException('FORBIDDEN_LEAD', 'Forbidden.');

        $clean = Validation::validate($r->all(), ['remark' => 'required|string']);
        $actRef = $this->leads->addActivity($ctx->franchiseRef, [
            'activity_ref'  => \App\Core\RefGenerator::generate('ACT'),
            'org_ref'       => $ctx->orgRef,
            'lead_ref'      => $ref,
            'user_ref'      => $ctx->userRef,
            'activity_type' => 'REMARK',
            'activity_note' => $clean['remark'],
        ]);

        return Response::json(201, ['activity_ref' => $actRef, 'remark' => $clean['remark']]);
    }

    // ── LED-004: Lead activity timeline ──────────────────────────

    /** Full activity timeline for a lead (all event types). */
    public function timeline(Request $r, ?string $ref = null): Response
    {
        $ref = $ref ?: (string)$r->param('ref');
        $ctx = TenantContext::get();
        $this->authorization->requirePermission($ctx, 'leads', 'view');
        $lead = $this->leads->findByRef($ctx->franchiseRef, $ref);
        if (!$lead) throw new NotFoundException('LEAD_NOT_FOUND', 'Lead not found.');
        if (!SalesLeadPolicy::canView($ctx, $lead)) throw new ForbiddenException('FORBIDDEN_LEAD', 'Forbidden.');

        $activities = $this->leads->getActivities($ctx->franchiseRef, $ref);
        return Response::json(200, $activities);
    }

    // ── LED-005: Lead-specific follow-ups ────────────────────────

    /** List follow-ups for this lead. */
    public function followUps(Request $r, ?string $ref = null): Response
    {
        $ref = $ref ?: (string)$r->param('ref');
        $ctx = TenantContext::get();
        $this->authorization->requirePermission($ctx, 'leads', 'view');
        $lead = $this->leads->findByRef($ctx->franchiseRef, $ref);
        if (!$lead) throw new NotFoundException('LEAD_NOT_FOUND', 'Lead not found.');
        if (!SalesLeadPolicy::canView($ctx, $lead)) throw new ForbiddenException('FORBIDDEN_LEAD', 'Forbidden.');

        $followUpRepo = \App\Core\Container::getInstance()->make(\App\Repositories\Contracts\FollowUpRepositoryInterface::class);
        $res = $followUpRepo->list($ctx->franchiseRef, ['lead_ref' => $ref], 1, 100);
        return Response::json(200, $res['items'] ?? []);
    }

    // ── LED-015: Duplicate check ─────────────────────────────────

    /** Check duplicates by mobile/GSTIN/email. */
    public function duplicateCheck(Request $r): Response
    {
        $ctx = TenantContext::get();
        $this->authorization->requirePermission($ctx, 'leads', 'view');
        $mobile = $r->query('mobile', '');
        $email  = $r->query('email', '');
        $gstin  = $r->query('gstin', '');

        $results = [];
        if ($mobile !== '') {
            $norm = MobileNormalizer::normalize($mobile);
            $match = $this->leads->findByMobile($ctx->franchiseRef, $norm);
            if ($match) $results[] = $match;
        }

        return Response::json(200, [
            'duplicates' => $results,
            'count'      => count($results),
        ]);
    }

    // ── LED-011: Bulk assign ─────────────────────────────────────

    /** Bulk assign leads to a user. */
    public function bulkAssign(Request $r): Response
    {
        $ctx = TenantContext::get();
        if (!$ctx->can('leads', 'assign')) {
            throw new ForbiddenException('FORBIDDEN', 'Permission required: leads.assign');
        }
        $clean = Validation::validate($r->all(), [
            'lead_refs'         => 'required',
            'assigned_user_ref' => 'required|string',
        ]);

        $leadRefs = $clean['lead_refs'];
        if (!is_array($leadRefs)) {
            $leadRefs = [$leadRefs];
        }

        $count = 0;
        foreach ($leadRefs as $leadRef) {
            $this->leadService->reassign($ctx->franchiseRef, (string)$leadRef, $clean['assigned_user_ref'], $ctx->userRef, 'Bulk assignment');
            $count++;
        }

        return Response::json(200, ['assigned' => $count]);
    }
}
