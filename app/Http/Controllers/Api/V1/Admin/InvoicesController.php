<?php
declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Core\{QueryParams, Request, Response, TenantContext, Validation};
use App\Core\Exceptions\NotFoundException;
use App\Domain\Audit\AuditService;
use App\Domain\Authorization\AuthorizationService;
use App\Domain\Billing\BillingService;
use App\Repositories\Contracts\{InvoiceRepositoryInterface, PartyRepositoryInterface};

final class InvoicesController
{
    public function __construct(private InvoiceRepositoryInterface $invoices, private PartyRepositoryInterface $parties, private BillingService $billing, private AuthorizationService $authorization, private AuditService $audit) {}

    public function index(Request $r): Response
    {
        $ctx = TenantContext::get(); $f = $ctx->requireFranchise(); $this->authorization->requirePermission($ctx, 'billing', 'view');
        $q = QueryParams::fromRequest($r, ['created_at','invoice_date','grand_total','status']);
        if ($ctx->scopeFor('billing') === 'NONE') return Response::json(200, [], ['page' => $q['page'], 'per_page' => $q['per_page'], 'total' => 0, 'total_pages' => 0]);
        $partyRef = $ctx->isDistributor() ? $ctx->partyRef : null;
        $result = $this->invoices->list($f, ['status' => $q['status'], 'search' => $q['search']], $q['page'], $q['per_page'], $partyRef);
        // The repository cannot safely pre-filter OWN/TEAM/TERRITORY without
        // duplicating policy joins; enforce scope on every returned record.
        $visible = array_values(array_filter($result['data'], fn(array $invoice) => $this->canRead($ctx, $invoice)));
        return Response::json(200, $visible, ['page' => $q['page'], 'per_page' => $q['per_page'], 'total' => count($visible), 'total_pages' => (int)ceil(count($visible) / $q['per_page'])]);
    }

    public function show(Request $r): Response
    {
        $ctx = TenantContext::get(); $f = $ctx->requireFranchise(); $this->authorization->requirePermission($ctx, 'billing', 'view');
        $ref = (string)$r->param('ref'); $invoice = $this->invoices->findByRef($f, $ref);
        if (!$invoice || !$this->canRead($ctx, $invoice)) throw new NotFoundException('INVOICE_NOT_FOUND', 'Invoice not found.');
        $invoice['items'] = $this->invoices->getItems($f, $ref); return Response::json(200, $invoice);
    }

    public function byOrder(Request $r): Response
    {
        $ctx = TenantContext::get(); $f = $ctx->requireFranchise(); $this->authorization->requirePermission($ctx, 'billing', 'view');
        $invoice = $this->invoices->findByOrderRef($f, (string)$r->param('order_ref'));
        if (!$invoice || !$this->canRead($ctx, $invoice)) throw new NotFoundException('INVOICE_NOT_FOUND', 'Invoice not found.');
        return Response::json(200, $invoice);
    }

    public function generate(Request $r): Response
    {
        $ctx = TenantContext::get(); $this->authorization->requirePermission($ctx, 'billing', 'create');
        $orderRef = Validation::validate($r->all(), ['order_ref' => 'required|string'])['order_ref'];
        $result = $this->billing->generateInvoice($ctx->orgRef, $ctx->requireFranchise(), $orderRef, $ctx->userRef);
        $this->audit->log(ctx: $ctx, category: 'BILLING', action: 'invoice.generated', entityType: 'invoice', entityRef: $result['invoice_ref'], after: $result);
        return Response::json(201, $result);
    }

    public function cancel(Request $r): Response
    {
        $ctx = TenantContext::get(); $f = $ctx->requireFranchise(); $this->authorization->requirePermission($ctx, 'billing', 'cancel');
        $ref = (string)$r->param('ref'); $invoice = $this->invoices->findByRef($f, $ref);
        if (!$invoice || !$this->canRead($ctx, $invoice)) throw new NotFoundException('INVOICE_NOT_FOUND', 'Invoice not found.');
        $reason = Validation::validate($r->all(), ['reason' => 'required|string|min:2'])['reason'];
        $result = $this->billing->cancelInvoice($f, $ref, $ctx->userRef, $reason);
        $this->audit->log(ctx: $ctx, category: 'BILLING', action: 'invoice.cancelled', entityType: 'invoice', entityRef: $ref, before: $invoice, after: $result, reason: $reason);
        return Response::json(200, $result);
    }

    private function canRead(TenantContext $ctx, array $invoice): bool
    {
        if ($ctx->isDistributor() && ($invoice['party_ref'] ?? null) !== $ctx->partyRef) return false;
        try {
            $territory = $this->parties->findTerritoryRefs($ctx->requireFranchise(), (string)$invoice['party_ref'])[0] ?? null;
            $this->authorization->requireRecordScope($ctx, 'billing', $invoice['sales_user_ref'] ?? null, $territory, $invoice['franchise_ref'] ?? null);
            return true;
        } catch (NotFoundException) { return false; }
    }
}
