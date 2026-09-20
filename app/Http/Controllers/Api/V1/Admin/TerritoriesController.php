<?php
declare(strict_types=1);
namespace App\Http\Controllers\Api\V1\Admin;

use App\Core\Request;
use App\Core\Response;
use App\Core\Validation;
use App\Core\TenantContext;
use App\Domain\Territory\TerritoryService;
use App\Domain\Territory\TerritoryValidator;
use App\Repositories\Contracts\PartyTerritoryRepositoryInterface;

final class TerritoriesController
{
    public function __construct(
        private PartyTerritoryRepositoryInterface $territories,
        private TerritoryService $territoryService,
        private TerritoryValidator $validator,
    ) {}

    public function index(Request $r): Response
    {
        $ctx = TenantContext::get();
        $partyRef = (string)$r->query('party_ref', '');
        $items = $this->territories->listForParty($ctx->franchiseRef, $partyRef);
        return Response::json(['data' => ['items' => $items]]);
    }

    public function store(Request $r): Response
    {
        $ctx = TenantContext::get();
        $clean = Validation::validate($r->all(), [
            'party_ref'      => 'required|string',
            'level'          => 'required|string',
            'effective_from' => 'required|string',
        ]);

        $data = array_merge($r->all(), [
            'org_ref'        => $ctx->orgRef,
            'franchise_ref'  => $ctx->franchiseRef,
            'created_by_ref' => $ctx->userRef,
        ]);

        $ref = $this->territoryService->create($data);
        return Response::json(['data' => $this->territories->findByRef($ctx->franchiseRef, $ref)], 201);
    }

    public function validate(Request $r): Response
    {
        $ctx = TenantContext::get();
        $clean = Validation::validate($r->all(), [
            'party_ref' => 'required|string',
            'pincode'   => 'required|string',
        ]);

        $result = $this->validator->validate(
            $ctx->franchiseRef,
            $clean['party_ref'],
            $clean['pincode'],
            $r->input('date')
        );

        return Response::json(['data' => $result]);
    }

    public function override(Request $r): Response
    {
        $ctx = TenantContext::get();
        $clean = Validation::validate($r->all(), [
            'order_ref' => 'required|string',
            'party_ref' => 'required|string',
            'pincode'   => 'required|string',
            'reason'    => 'required|string',
        ]);

        $data = array_merge($r->all(), [
            'org_ref'         => $ctx->orgRef,
            'franchise_ref'   => $ctx->franchiseRef,
            'approved_by_ref' => $ctx->userRef,
        ]);

        $overrideRef = $this->territoryService->createOverride($data);
        return Response::json(['data' => ['override_ref' => $overrideRef, 'status' => 'OVERRIDDEN']], 201);
    }
}
