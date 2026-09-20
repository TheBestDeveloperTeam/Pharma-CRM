<?php
declare(strict_types=1);
namespace App\Http\Controllers\Api\V1\Admin;

use App\Core\Request;
use App\Core\Response;
use App\Core\Validation;
use App\Core\TenantContext;
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
        $page = (int)$r->query('page', 1);
        $perPage = min((int)$r->query('per_page', 20), 100);

        $partyRef = ($ctx->role === 'DISTRIBUTOR') ? $ctx->partyRef : $r->query('party_ref');

        $filters = [
            'mode'   => $r->query('mode'),
            'status' => $r->query('status'),
            'search' => $r->query('search'),
        ];

        $res = $this->paymentRepo->list($franchiseRef, $filters, $page, $perPage, $partyRef);
        return Response::json(['data' => $res]);
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
