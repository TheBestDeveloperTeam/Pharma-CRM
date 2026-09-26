<?php
declare(strict_types=1);
namespace App\Http\Controllers\Api\V1\Admin;

use App\Core\{Request, Response, Validation, TenantContext, QueryParams};
use App\Core\Exceptions\NotFoundException;
use App\Domain\Payments\PaymentService;
use App\Domain\Payments\AllocationService;
use App\Domain\Payments\PaymentReversalService;
use App\Domain\Authorization\AuthorizationService;
use App\Domain\Audit\AuditService;
use App\Repositories\Contracts\PaymentRepositoryInterface;

final class PaymentsController
{
    public function __construct(
        private PaymentRepositoryInterface $paymentRepo,
        private PaymentService $paymentService,
        private AllocationService $allocations,
        private PaymentReversalService $reversals,
        private AuthorizationService $authorization,
        private AuditService $audit,
    ) {}

    public function index(Request $r): Response
    {
        $ctx = TenantContext::get();
        $this->authorization->requirePermission($ctx, 'payments', 'view');
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
        $this->authorization->requirePermission($ctx, 'payments', 'view');
        $payment = $this->paymentRepo->findByRef($ctx->franchiseRef, $ref);
        if (!$payment) {
            throw new NotFoundException('PAYMENT_NOT_FOUND', 'Payment not found.');
        }

        return Response::json(['data' => $payment]);
    }

    public function store(Request $r): Response
    {
        $ctx = TenantContext::get();
        $this->authorization->requirePermission($ctx, 'payments', 'create');
        $clean = Validation::validate($r->all(), [
            'party_ref'    => 'required|string',
            'payment_date' => 'required|string',
            'amount'       => 'required|numeric',
            'mode'         => 'required|string',
        ]);

        // Automatic/FIFO allocation remains an unresolved business policy.
        $autoAllocate = false;

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

        $this->audit->log(ctx: $ctx, category: 'BUSINESS', action: 'payment.created', entityType: 'payment', entityRef: $res['payment_ref'], after: $res);
        return Response::json(201, $res);
    }

    public function allocate(Request $r): Response
    {
        $ctx = TenantContext::get(); $this->authorization->requirePermission($ctx, 'payments', 'allocate');
        $data = Validation::validate($r->all(), ['invoice_ref' => 'required|string', 'amount' => 'required|numeric']);
        $paymentRef = (string)$r->param('ref'); $result = $this->allocations->allocate($ctx, $paymentRef, $data['invoice_ref'], (string)$data['amount'], $ctx->userRef);
        $this->audit->log(ctx: $ctx, category: 'BUSINESS', action: 'payment.allocated', entityType: 'payment_allocation', entityRef: $result['allocation_ref'], after: $result); return Response::json(201, $result);
    }

    public function reverse(Request $r): Response
    {
        $ctx = TenantContext::get(); $this->authorization->requirePermission($ctx, 'payments', 'reverse');
        $data = Validation::validate($r->all(), ['reason' => 'required|string|min:2', 'idempotency_key' => 'required|string']);
        $paymentRef = (string)$r->param('ref'); $payment = $this->paymentRepo->findByRef($ctx->requireFranchise(), $paymentRef);
        if (!$payment) throw new NotFoundException('PAYMENT_NOT_FOUND', 'Payment not found.');
        $result = $this->reversals->reverse($ctx->requireFranchise(), $paymentRef, $ctx->userRef, $data['reason'], $data['idempotency_key']);
        $this->audit->log(ctx: $ctx, category: 'BUSINESS', action: 'payment.reversed', entityType: 'payment', entityRef: $paymentRef, before: $payment, after: $result, reason: $data['reason']);
        return Response::json(201, $result);
    }
}
