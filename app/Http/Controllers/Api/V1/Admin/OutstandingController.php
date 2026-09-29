<?php
declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Core\{Request, Response, TenantContext, Container};
use App\Core\Exceptions\NotFoundException;
use App\Domain\Authorization\AuthorizationService;
use App\Domain\Payments\OutstandingService;

final class OutstandingController
{
    public function __construct(
        private OutstandingService $outstanding,
        private AuthorizationService $authorization
    ) {}

    public function index(Request $request): Response
    {
        $ctx = TenantContext::get();
        $this->authorization->requirePermission($ctx, 'payments', 'view');
        $filters = [
            'party_ref' => $ctx->isPartyBound() ? $ctx->partyRef : $request->query('party_ref'),
            'from'      => $request->query('from'),
            'to'        => $request->query('to'),
            'bucket'    => $request->query('bucket'),
            'page'      => $request->query('page', 1),
            'per_page'  => $request->query('per_page', 25),
            'sort_by'   => $request->query('sort_by'),
            'sort_dir'  => $request->query('sort_dir')
        ];
        $result = $this->outstanding->pageForContext($ctx, $filters);
        return Response::json(200, $result['rows'], $result['meta']);
    }

    public function ageing(Request $request): Response
    {
        $ctx = TenantContext::get();
        $this->authorization->requirePermission($ctx, 'payments', 'view');
        return Response::json(200, $this->outstanding->dashboardForContext($ctx));
    }

    public function partyOutstanding(Request $request): Response
    {
        $ctx = TenantContext::get();
        $this->authorization->requirePermission($ctx, 'payments', 'view');
        $f = $ctx->requireFranchise();
        $partyRef = (string)$request->param('party_ref');

        if ($ctx->isPartyBound() && $ctx->partyRef !== $partyRef) {
            throw new NotFoundException('PARTY_NOT_FOUND', 'Party not found.');
        }

        $pdo = Container::getInstance()->make(\PDO::class);
        $stmt = $pdo->prepare("SELECT party_ref, firm_name, credit_limit, payment_terms_days, status FROM parties WHERE franchise_ref = ? AND party_ref = ? LIMIT 1");
        $stmt->execute([$f, $partyRef]);
        $party = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (!$party) throw new NotFoundException('PARTY_NOT_FOUND', 'Party not found.');

        $invoices = $this->outstanding->rowsForContext($ctx, $partyRef);
        $totalOutstanding = 0.0;
        foreach ($invoices as $inv) {
            $totalOutstanding += (float)($inv['balance'] ?? 0.0);
        }

        $creditLimit = (float)($party['credit_limit'] ?? 0.0);
        $availableCredit = max(0.0, $creditLimit - $totalOutstanding);

        return Response::json(200, [
            'party_ref'           => $partyRef,
            'firm_name'           => $party['firm_name'],
            'credit_limit'        => $creditLimit,
            'payment_terms_days'  => (int)($party['payment_terms_days'] ?? 0),
            'current_outstanding' => $totalOutstanding,
            'available_credit'    => $availableCredit,
            'open_invoices_count' => count($invoices),
            'invoices'            => $invoices,
        ]);
    }
}
