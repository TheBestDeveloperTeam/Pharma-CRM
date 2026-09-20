<?php
declare(strict_types=1);
namespace App\Http\Controllers\Api\V1\Admin;

use App\Core\Request;
use App\Core\Response;
use App\Core\Validation;
use App\Core\TenantContext;
use App\Core\Exceptions\NotFoundException;
use App\Domain\Dispatch\DispatchService;
use App\Repositories\Contracts\DispatchRepositoryInterface;

final class DispatchesController
{
    public function __construct(
        private DispatchRepositoryInterface $dispatchRepo,
        private DispatchService $dispatchService,
    ) {}

    public function index(Request $r): Response
    {
        $ctx = TenantContext::get();
        $franchiseRef = $ctx->franchiseRef;
        $page = (int)$r->query('page', 1);
        $perPage = min((int)$r->query('per_page', 20), 100);

        $filters = [
            'status' => $r->query('status'),
            'search' => $r->query('search'),
        ];

        $res = $this->dispatchRepo->list($franchiseRef, $filters, $page, $perPage);
        return Response::json(['data' => $res]);
    }

    public function show(Request $r, string $ref): Response
    {
        $ctx = TenantContext::get();
        $dispatch = $this->dispatchRepo->findByRef($ctx->franchiseRef, $ref);
        if (!$dispatch) {
            throw new NotFoundException('DISPATCH_NOT_FOUND', 'Dispatch record not found.');
        }

        return Response::json(['data' => $dispatch]);
    }

    public function store(Request $r): Response
    {
        $ctx = TenantContext::get();
        $clean = Validation::validate($r->all(), [
            'invoice_ref' => 'required|string',
        ]);

        $res = $this->dispatchService->createDispatch(
            $ctx->orgRef,
            $ctx->franchiseRef,
            $clean['invoice_ref'],
            $r->input('transporter_ref'),
            $r->input('lr_number'),
            $r->input('tracking_url'),
            (int)$r->input('boxes', 1),
            $r->input('remarks'),
            $ctx->userRef
        );

        return Response::json(['data' => $res], 201);
    }

    public function deliver(Request $r, string $ref): Response
    {
        $ctx = TenantContext::get();
        $this->dispatchService->markDelivered($ctx->franchiseRef, $ref, $ctx->userRef);
        return Response::json(['data' => ['dispatch_ref' => $ref, 'status' => 'DELIVERED']]);
    }
}
