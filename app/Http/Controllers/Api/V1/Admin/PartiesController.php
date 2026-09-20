<?php
declare(strict_types=1);
namespace App\Http\Controllers\Api\V1\Admin;

use App\Core\Request;
use App\Core\Response;
use App\Core\Validation;
use App\Core\TenantContext;
use App\Core\Exceptions\NotFoundException;
use App\Domain\Parties\PartyService;
use App\Repositories\Contracts\PartyRepositoryInterface;

final class PartiesController
{
    public function __construct(
        private PartyRepositoryInterface $parties,
        private PartyService $partyService,
    ) {}

    public function index(Request $r): Response
    {
        $ctx = TenantContext::get();
        $franchiseRef = $ctx->franchiseRef;
        $page = (int)$r->query('page', 1);
        $perPage = min((int)$r->query('per_page', 20), 100);

        $salesUser = ($ctx->role === 'SALES') ? $ctx->userRef : null;

        $filters = [
            'status' => $r->query('status'),
            'search' => $r->query('search'),
        ];

        $res = $this->parties->list($franchiseRef, $filters, $page, $perPage, $salesUser);
        return Response::json(['data' => $res]);
    }

    public function show(Request $r, string $ref): Response
    {
        $ctx = TenantContext::get();
        $party = $this->parties->findByRef($ctx->franchiseRef, $ref);

        if (!$party) {
            throw new NotFoundException('PARTY_NOT_FOUND', 'Party not found.');
        }

        return Response::json(['data' => $party]);
    }

    public function store(Request $r): Response
    {
        $ctx = TenantContext::get();
        $clean = Validation::validate($r->all(), [
            'firm_name' => 'required|string',
        ]);

        $data = array_merge($r->all(), [
            'org_ref'        => $ctx->orgRef,
            'franchise_ref'  => $ctx->franchiseRef,
            'created_by_ref' => $ctx->userRef,
        ]);

        $partyRef = $this->partyService->create($data);
        return Response::json(['data' => $this->parties->findByRef($ctx->franchiseRef, $partyRef)], 201);
    }

    public function update(Request $r, string $ref): Response
    {
        $ctx = TenantContext::get();
        $data = $r->all();
        $data['updated_by_ref'] = $ctx->userRef;

        $this->partyService->update($ctx->franchiseRef, $ref, $data);
        return Response::json(['data' => $this->parties->findByRef($ctx->franchiseRef, $ref)]);
    }

    public function archive(Request $r, string $ref): Response
    {
        $ctx = TenantContext::get();
        $this->partyService->archive($ctx->franchiseRef, $ref);
        return Response::json(['data' => ['status' => 'ARCHIVED', 'party_ref' => $ref]]);
    }

    public function ledger(Request $r, string $ref): Response
    {
        $ctx = TenantContext::get();
        $summary = $this->parties->getLedgerSummary($ctx->franchiseRef, $ref);
        return Response::json(['data' => $summary]);
    }
}
