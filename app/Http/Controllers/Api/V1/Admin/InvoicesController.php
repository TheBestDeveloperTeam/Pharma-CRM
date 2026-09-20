<?php
declare(strict_types=1);
namespace App\Http\Controllers\Api\V1\Admin;

use App\Core\Request;
use App\Core\Response;
use App\Core\Validation;
use App\Core\TenantContext;
use App\Core\Exceptions\NotFoundException;
use App\Domain\Billing\BillingService;
use App\Repositories\Contracts\InvoiceRepositoryInterface;

final class InvoicesController
{
    public function __construct(
        private InvoiceRepositoryInterface $invoiceRepo,
        private BillingService $billingService,
    ) {}

    public function index(Request $r): Response
    {
        $ctx = TenantContext::get();
        $franchiseRef = $ctx->franchiseRef;
        $page = (int)$r->query('page', 1);
        $perPage = min((int)$r->query('per_page', 20), 100);

        $partyRef = ($ctx->role === 'DISTRIBUTOR') ? $ctx->partyRef : $r->query('party_ref');

        $filters = [
            'status' => $r->query('status'),
            'search' => $r->query('search'),
        ];

        $res = $this->invoiceRepo->list($franchiseRef, $filters, $page, $perPage, $partyRef);
        return Response::json(['data' => $res]);
    }

    public function show(Request $r, string $ref): Response
    {
        $ctx = TenantContext::get();
        $invoice = $this->invoiceRepo->findByRef($ctx->franchiseRef, $ref);
        if (!$invoice) {
            throw new NotFoundException('INVOICE_NOT_FOUND', 'Invoice not found.');
        }

        $items = $this->invoiceRepo->getItems($ctx->franchiseRef, $ref);
        $invoice['items'] = $items;

        return Response::json(['data' => $invoice]);
    }

    public function generate(Request $r): Response
    {
        $ctx = TenantContext::get();
        $clean = Validation::validate($r->all(), [
            'order_ref' => 'required|string',
        ]);

        $res = $this->billingService->generateInvoice(
            $ctx->orgRef,
            $ctx->franchiseRef,
            $clean['order_ref'],
            $ctx->userRef
        );

        return Response::json(['data' => $res], 201);
    }
}
