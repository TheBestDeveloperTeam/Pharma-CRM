<?php
declare(strict_types=1);
namespace App\Http\Controllers\Api\V1\Admin;

use App\Core\Request;
use App\Core\Response;
use App\Core\Validation;
use App\Core\TenantContext;
use App\Core\Exceptions\ForbiddenException;
use App\Core\Exceptions\NotFoundException;
use App\Domain\Leads\LeadService;
use App\Domain\Leads\MobileNormalizer;
use App\Repositories\Contracts\LeadRepositoryInterface;
use App\Policies\SalesLeadPolicy;

final class LeadsController
{
    public function __construct(
        private LeadRepositoryInterface $leads,
        private LeadService $leadService,
    ) {}

    public function index(Request $r): Response
    {
        $ctx = TenantContext::get();
        $franchiseRef = $ctx->franchiseRef;
        $page = (int)$r->query('page', 1);
        $perPage = min((int)$r->query('per_page', 20), 100);

        $assignedUser = ($ctx->role === 'SALES') ? $ctx->userRef : null;

        $filters = [
            'status' => $r->query('status'),
            'search' => $r->query('search'),
        ];

        $res = $this->leads->list($franchiseRef, $filters, $page, $perPage, $assignedUser);
        return Response::json(['data' => $res]);
    }

    public function show(Request $r, string $ref): Response
    {
        $ctx = TenantContext::get();
        $lead = $this->leads->findByRef($ctx->franchiseRef, $ref);

        if (!$lead) {
            throw new NotFoundException('LEAD_NOT_FOUND', 'Lead not found.');
        }

        if (!SalesLeadPolicy::canView($ctx, $lead)) {
            throw new ForbiddenException('FORBIDDEN_LEAD', 'You do not have permission to view this lead.');
        }

        $activities = $this->leads->getActivities($ctx->franchiseRef, $ref);
        $lead['activities'] = $activities;

        return Response::json(['data' => $lead]);
    }

    public function store(Request $r): Response
    {
        $ctx = TenantContext::get();
        $clean = Validation::validate($r->all(), [
            'contact_name' => 'required|string',
            'mobile'       => 'required|string',
        ]);

        $data = array_merge($r->all(), [
            'org_ref'        => $ctx->orgRef,
            'franchise_ref'  => $ctx->franchiseRef,
            'created_by_ref' => $ctx->userRef,
        ]);

        if ($ctx->role === 'SALES') {
            $data['assigned_user_ref'] = $ctx->userRef;
        }

        $leadRef = $this->leadService->create($data);
        $lead = $this->leads->findByRef($ctx->franchiseRef, $leadRef);

        return Response::json(201, $lead);
    }

    public function update(Request $r, string $ref): Response
    {
        $ctx = TenantContext::get();
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
        return Response::json(['data' => $this->leads->findByRef($ctx->franchiseRef, $ref)]);
    }

    public function status(Request $r, string $ref): Response
    {
        $ctx = TenantContext::get();
        $clean = Validation::validate($r->all(), [
            'status' => 'required|string',
        ]);

        $note = $r->input('note');
        $this->leadService->changeStatus($ctx->franchiseRef, $ref, $clean['status'], $ctx->userRef, $note);

        return Response::json(['data' => $this->leads->findByRef($ctx->franchiseRef, $ref)]);
    }

    public function assign(Request $r, string $ref): Response
    {
        $ctx = TenantContext::get();
        if (!$ctx->isFranchiseAdmin() && !$ctx->isSuperAdmin()) {
            throw new ForbiddenException('FORBIDDEN', 'Only Franchise Admin can reassign leads.');
        }

        $clean = Validation::validate($r->all(), [
            'assigned_user_ref' => 'required|string',
        ]);

        $reason = $r->input('reason');
        $this->leadService->reassign($ctx->franchiseRef, $ref, $clean['assigned_user_ref'], $ctx->userRef, $reason);

        return Response::json(['data' => $this->leads->findByRef($ctx->franchiseRef, $ref)]);
    }
}
