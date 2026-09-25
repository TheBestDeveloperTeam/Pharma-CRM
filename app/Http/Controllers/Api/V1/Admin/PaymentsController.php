<?php
declare(strict_types=1);
namespace App\Http\Controllers\Api\V1\Admin;

use App\Core\{Request, Response, Validation, TenantContext, QueryParams};
use App\Core\Exceptions\NotFoundException;
use App\Domain\Payments\PaymentService;
use App\Repositories\Contracts\PaymentRepositoryInterface;

final class PaymentsController
{
    public function __construct(
        private PaymentRepositoryInterface $paymentRepo,
        private PaymentService $paymentService,
    ) {}

    public function index(Request $r): Response
    {
        $ctx = TenantContext::get();
        $franchiseRef = $ctx->franchiseRef;
        $query = QueryParams::fromRequest($r, ['created_at', 'payment_date', 'amount']);
        $page = $query['page'];
        $perPage = $query['per_page'];

        $partyRef = ($ctx->role === 'DISTRIBUTOR') ? $ctx->partyRef : $r->query('party_ref');

        $filters = [
            'mode'   => $r->query('mode'),
            'status' => $query['status'],
            'search' => $query['search'],
        ];

        $res = $this->paymentRepo->list($franchiseRef, $filters, $page, $perPage, $partyRef);
        return Response::json(200, $res['data'], $res['meta']);
    }

    public function show(Request $r, string $ref): Response
    {
        $ctx = TenantContext::get();
        $payment = $this->paymentRepo->findByRef($ctx->franchiseRef, $ref);
        if (!$payment) {
            throw new NotFoundException('PAYMENT_NOT_FOUND', 'Payment not found.');
        }

        return Response::json(['data' => $payment]);
    }

    public function store(Request $r): Response
    {
        $ctx = TenantContext::get();
        $clean = Validation::validate($r->all(), [
            'party_ref'    => 'required|string',
            'payment_date' => 'required|string',
            'amount'       => 'required|numeric',
            'mode'         => 'required|string',
        ]);

        $autoAllocate = (bool)$r->input('auto_allocate', true);

        $res = $this->paymentService->recordPayment(
            $ctx->orgRef,
            $ctx->franchiseRef,
            $clean['party_ref'],
            $clean['payment_date'],
            (float)$clean['amount'],
            $clean['mode'],
            $r->input('reference_no'),
            $r->input('remarks'),
            $autoAllocate,
            $ctx->userRef
        );

        return Response::json(['data' => $res], 201);
    }
}
