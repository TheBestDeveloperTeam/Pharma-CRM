<?php
declare(strict_types=1);
namespace App\Http\Controllers\Api\V1\Admin;

use App\Core\{Database, QueryParams, Request, Response, TenantContext, Validation};
use App\Core\Exceptions\{ConflictException, NotFoundException, ValidationException};
use App\Domain\Audit\AuditService;
use App\Domain\Authorization\AuthorizationService;
use App\Domain\Billing\GstCalculator;
use App\Domain\Orders\{CreditRuleService, OrderService};
use App\Domain\Pricing\PriceResolver;
use App\Domain\Schemes\SchemeCalculator;
use App\Repositories\Contracts\{OrderRepositoryInterface, PartyRepositoryInterface, ProductRepositoryInterface};
use App\Support\Money;

final class OrdersController
{
    public function __construct(
        private OrderRepositoryInterface $orderRepo,
        private PartyRepositoryInterface $parties,
        private OrderService $orderService,
        private AuthorizationService $authorization,
        private AuditService $audit,
        private ?ProductRepositoryInterface $productRepo = null,
        private ?PriceResolver $priceResolver = null,
        private ?SchemeCalculator $schemeCalculator = null,
        private ?CreditRuleService $creditRuleService = null,
        private ?GstCalculator $gstCalculator = null,
        private ?Database $db = null
    ) {
        $container = \App\Core\Container::getInstance();
        $this->productRepo = $this->productRepo ?? $container->make(ProductRepositoryInterface::class);
        $this->priceResolver = $this->priceResolver ?? $container->make(PriceResolver::class);
        $this->schemeCalculator = $this->schemeCalculator ?? $container->make(SchemeCalculator::class);
        $this->creditRuleService = $this->creditRuleService ?? $container->make(CreditRuleService::class);
        $this->gstCalculator = $this->gstCalculator ?? $container->make(GstCalculator::class);
        $this->db = $this->db ?? $container->make(Database::class);
    }

    public function index(Request $r): Response
    {
        $ctx = TenantContext::get(); $f = $ctx->requireFranchise(); $this->authorization->requirePermission($ctx, 'orders', 'view'); $q = QueryParams::fromRequest($r, ['created_at','order_date','grand_total','status']); $scope = $ctx->scopeFor('orders');
        if ($scope === 'NONE') return Response::json(200, [], ['page' => $q['page'], 'per_page' => $q['per_page'], 'total' => 0, 'total_pages' => 0]);
        $filters = ['status' => $q['status'], 'search' => $q['search'], 'sort_by' => $q['sort_by'], 'sort_dir' => $q['sort_dir']]; $partyRef = $ctx->isPartyBound() ? $ctx->partyRef : null;
        if ($scope === 'OWN') $filters['sales_user_ref'] = $ctx->userRef;
        if ($scope === 'TEAM') $filters['sales_user_refs'] = array_values(array_unique(array_merge([$ctx->userRef], $ctx->teamUserRefs)));
        if ($scope === 'TERRITORY') { if (!$ctx->territoryRefs) return Response::json(200, [], ['page' => $q['page'], 'per_page' => $q['per_page'], 'total' => 0, 'total_pages' => 0]); $filters['territory_refs'] = $ctx->territoryRefs; }
        $res = $this->orderRepo->list($f, $filters, $q['page'], $q['per_page'], $partyRef); return Response::json(200, $res['data'], $res['meta']);
    }

    public function show(Request $r): Response
    {
        $ctx = TenantContext::get(); $f = $ctx->requireFranchise(); $this->authorization->requirePermission($ctx, 'orders', 'view'); $ref = (string)$r->param('ref'); $order = $this->orderRepo->findByRef($f, $ref); if (!$order) throw new NotFoundException('ORDER_NOT_FOUND', 'Order not found.'); $this->checkScope($ctx, $order);
        $order['items'] = $this->orderRepo->getItems($f, $ref); $order['history'] = $this->orderRepo->getHistory($f, $ref); return Response::json(200, $order);
    }

    public function store(Request $r): Response
    {
        $ctx = TenantContext::get(); $this->authorization->requirePermission($ctx, 'orders', 'create'); $clean = Validation::validate($r->all(), ['party_ref' => 'required|string', 'client_order_ref' => 'required|string|min:1', 'channel' => 'required|enum:PORTAL,SALES,ADMIN', 'items' => 'required|array|min:1']);
        $sales = $ctx->scopeFor('orders') === 'ALL' ? ($r->input('sales_user_ref') ?: null) : $ctx->userRef; // B1 — scope decides, not users.role
        $res = $this->orderService->createOrder($ctx->orgRef, $ctx->requireFranchise(), $clean['party_ref'], $clean['client_order_ref'], $clean['channel'], $clean['items'], $sales, $r->input('shipping_address'), $r->input('shipping_pincode'), $r->input('remarks'), $ctx->userRef);
        $this->audit->log(ctx: $ctx, category: 'BUSINESS', action: 'order.draft_created', entityType: 'order', entityRef: $res['order_ref'], after: $res); return Response::json(201, $res);
    }

    public function updateDraft(Request $r): Response
    {
        $ctx = TenantContext::get(); $f = $ctx->requireFranchise(); $this->authorization->requirePermission($ctx, 'orders', 'editDraft'); $ref = (string)$r->param('ref'); $order = $this->orderRepo->findByRef($f, $ref); if (!$order) throw new NotFoundException('ORDER_NOT_FOUND', 'Order not found.'); $this->checkScope($ctx, $order);
        $clean = Validation::validate($r->all(), ['party_ref' => 'required|string', 'items' => 'required|array|min:1']); $res = $this->orderService->updateDraft($ctx->orgRef, $f, $ref, $clean['party_ref'], $clean['items'], $r->input('shipping_address'), $r->input('shipping_pincode'), $r->input('remarks'), $ctx->userRef); $this->audit->log(ctx: $ctx, category: 'BUSINESS', action: 'order.draft_updated', entityType: 'order', entityRef: $ref, after: $res); return Response::json(200, $res);
    }

    public function deleteDraft(Request $r): Response
    {
        $ctx = TenantContext::get(); $f = $ctx->requireFranchise(); $this->authorization->requirePermission($ctx, 'orders', 'deleteDraft'); $ref = (string)$r->param('ref'); $order = $this->orderRepo->findByRef($f, $ref); if (!$order) throw new NotFoundException('ORDER_NOT_FOUND', 'Order not found.'); $this->checkScope($ctx, $order); $this->orderService->deleteDraft($f, $ref); $this->audit->log(ctx: $ctx, category: 'BUSINESS', action: 'order.draft_deleted', entityType: 'order', entityRef: $ref, before: $order); return Response::json(200, ['order_ref' => $ref, 'status' => 'DELETED']);
    }

    public function submit(Request $r): Response
    {
        $ctx = TenantContext::get(); $f = $ctx->requireFranchise(); $this->authorization->requirePermission($ctx, 'orders', 'submit'); $ref = (string)$r->param('ref'); $order = $this->orderRepo->findByRef($f, $ref); if (!$order) throw new NotFoundException('ORDER_NOT_FOUND', 'Order not found.'); $this->checkScope($ctx, $order); $res = $this->orderService->submitOrder($ctx->orgRef, $f, $ref, $ctx->userRef); $this->audit->log(ctx: $ctx, category: 'BUSINESS', action: 'order.submitted', entityType: 'order', entityRef: $ref, after: $res); return Response::json(200, $res);
    }

    public function confirm(Request $r): Response
    {
        $ctx = TenantContext::get(); $f = $ctx->requireFranchise(); $this->authorization->requirePermission($ctx, 'orders', 'confirm'); $ref = (string)$r->param('ref'); $order = $this->orderRepo->findByRef($f, $ref); if (!$order) throw new NotFoundException('ORDER_NOT_FOUND', 'Order not found.'); $this->checkScope($ctx, $order); $res = $this->orderService->confirmOrder($ctx->orgRef, $f, $ref, $ctx->userRef); $this->audit->log(ctx: $ctx, category: 'BUSINESS', action: 'order.confirmed', entityType: 'order', entityRef: $ref, after: $res); return Response::json(200, $res);
    }

    public function cancel(Request $r): Response
    {
        $ctx = TenantContext::get(); $f = $ctx->requireFranchise(); $this->authorization->requirePermission($ctx, 'orders', 'cancel'); $ref = (string)$r->param('ref'); $order = $this->orderRepo->findByRef($f, $ref); if (!$order) throw new NotFoundException('ORDER_NOT_FOUND', 'Order not found.'); $this->checkScope($ctx, $order); $reason = Validation::validate($r->all(), ['reason' => 'required|string|min:2'])['reason']; $this->orderService->cancelOrder($ctx->orgRef, $f, $ref, $ctx->userRef, $reason); $this->audit->log(ctx: $ctx, category: 'BUSINESS', action: 'order.cancelled', entityType: 'order', entityRef: $ref, before: $order, after: ['status' => 'CANCELLED', 'reason' => $reason]); return Response::json(200, ['order_ref' => $ref, 'status' => 'CANCELLED']);
    }

    public function calculate(Request $r): Response
    {
        $ctx = TenantContext::get();
        $f = $ctx->requireFranchise();
        $this->authorization->requirePermission($ctx, 'orders', 'view');

        $clean = Validation::validate($r->all(), [
            'party_ref' => 'required|string',
            'items'     => 'required|array|min:1',
        ]);

        $partyRef = (string)$clean['party_ref'];
        $party = $this->parties->findByRef($f, $partyRef);
        if (!$party) {
            throw new NotFoundException('PARTY_NOT_FOUND', 'Party not found.');
        }

        if ($ctx->isPartyBound() && $ctx->partyRef !== $partyRef) {
            throw new NotFoundException('PARTY_NOT_FOUND', 'Party not found.');
        }

        $tierRef = $r->input('tier_ref') ?: ($party['tier_ref'] ?? null);
        $today = date('Y-m-d');

        // Determine GST jurisdiction (intra vs inter state)
        $supplierState = $this->db->fetchColumn("SELECT state_ref FROM franchises WHERE franchise_ref = ? LIMIT 1", [$f]);
        if (!$supplierState) {
            $supplierState = $this->db->fetchColumn("SELECT state_ref FROM states WHERE status = 'ACTIVE' ORDER BY id ASC LIMIT 1") ?: 'STA-MAHARASHTRA00001';
        }

        $shippingPincode = (string)($r->input('shipping_pincode') ?: ($party['pincode'] ?? ''));
        $placeState = null;
        if ($shippingPincode !== '') {
            $placeState = $this->db->fetchColumn("SELECT state_ref FROM pincodes WHERE pincode = ? LIMIT 1", [$shippingPincode]);
        }
        if (!$placeState && !empty($party['state_ref'])) {
            $placeState = $party['state_ref'];
        }
        if (!$placeState) {
            $placeState = $supplierState;
        }

        $jurisdiction = ($supplierState === $placeState) ? GstCalculator::INTRASTATE : GstCalculator::INTERSTATE;

        $calcItems = [];
        $taxInput = [];
        $rawLines = [];

        foreach ($clean['items'] as $index => $item) {
            $prodRef = (string)($item['product_ref'] ?? '');
            $qty = (int)($item['qty'] ?? $item['paid_qty'] ?? 0);

            if ($prodRef === '' || $qty <= 0) {
                throw new ValidationException('INVALID_ITEM', "Each item requires a valid product_ref and quantity > 0.");
            }

            $product = $this->productRepo->findByRef($f, $prodRef);
            if (!$product || ($product['status'] ?? '') !== 'ACTIVE') {
                throw new ValidationException('INVALID_PRODUCT', "Product {$prodRef} is inactive or not found.");
            }

            $resolved = $this->priceResolver->resolve($f, $prodRef, $partyRef, $tierRef, $today);
            $unitRate = (string)$resolved['rate'];
            $ratePaise = Money::fromDecimal($unitRate);
            $lineSubtotalPaise = $ratePaise * $qty;
            $lineSubtotal = Money::toDecimal($lineSubtotalPaise);

            $scheme = $this->schemeCalculator->calculate($f, $tierRef, $prodRef, $qty, $today);
            $freeQty = (int)($scheme['free_qty'] ?? 0);

            $gstPercent = (string)($product['gst_percent'] ?? $product['gst_rate'] ?? '0.00');

            $taxInput[] = [
                'taxable_amount' => $lineSubtotal,
                'gst_percent'    => $gstPercent,
            ];

            $rawLines[] = [
                'product_ref'  => $prodRef,
                'product_name' => $product['product_name'] ?? '',
                'sku'          => $product['sku'] ?? '',
                'hsn_code'     => $product['hsn_code'] ?? '',
                'paid_qty'     => $qty,
                'free_qty'     => $freeQty,
                'unit_rate'    => $unitRate,
                'rate_source'  => $resolved['rate_source'],
                'price_ref'    => $resolved['price_ref'] ?? null,
                'scheme_ref'   => $scheme['scheme_refs'][0] ?? null,
                'scheme_name'  => $scheme['scheme_name'] ?? null,
                'gst_percent'  => $gstPercent,
            ];
        }

        $taxResult = $this->gstCalculator->calculate($taxInput, $jurisdiction);

        foreach ($rawLines as $i => $raw) {
            $t = $taxResult['lines'][$i];
            $calcItems[] = [
                'product_ref'    => $raw['product_ref'],
                'product_name'   => $raw['product_name'],
                'sku'            => $raw['sku'],
                'hsn_code'       => $raw['hsn_code'],
                'paid_qty'       => $raw['paid_qty'],
                'free_qty'       => $raw['free_qty'],
                'unit_rate'      => $raw['unit_rate'],
                'rate_source'    => $raw['rate_source'],
                'price_ref'      => $raw['price_ref'],
                'taxable_amount' => $t['taxable_amount'],
                'gst_percent'    => $raw['gst_percent'],
                'cgst_amount'    => $t['cgst_amount'],
                'sgst_amount'    => $t['sgst_amount'],
                'igst_amount'    => $t['igst_amount'],
                'total_tax'      => $t['total_tax'],
                'line_total'     => $t['line_total'],
                'scheme'         => $raw['scheme_ref'] ? [
                    'scheme_ref'  => $raw['scheme_ref'],
                    'scheme_name' => $raw['scheme_name'],
                    'free_qty'    => $raw['free_qty'],
                ] : null,
            ];
        }

        // Credit check
        $grandTotalPaise = Money::fromDecimal($taxResult['grand_total']);
        $currentOutstandingPaise = $this->creditRuleService->getPartyOutstanding($f, $partyRef);
        $creditLimitPaise = Money::fromDecimal((string)($party['credit_limit'] ?? '0.00'));
        $projectedOutstandingPaise = $currentOutstandingPaise + $grandTotalPaise;
        $creditCheck = $this->creditRuleService->checkCredit($f, $partyRef, $grandTotalPaise);

        $availableCreditPaise = max(0, $creditLimitPaise - $currentOutstandingPaise);
        $postOrderAvailablePaise = max(0, $creditLimitPaise - $projectedOutstandingPaise);

        return Response::json(200, [
            'party' => [
                'party_ref'    => $partyRef,
                'firm_name'    => $party['firm_name'] ?? '',
                'tier_ref'     => $tierRef,
                'gstin'        => $party['gstin'] ?? null,
                'credit_limit' => Money::toDecimal($creditLimitPaise),
            ],
            'jurisdiction'          => $jurisdiction,
            'supplier_state_ref'    => $supplierState,
            'place_of_supply_state' => $placeState,
            'items'                 => $calcItems,
            'subtotal'              => $taxResult['taxable_total'],
            'cgst_total'            => $taxResult['cgst_total'],
            'sgst_total'            => $taxResult['sgst_total'],
            'igst_total'            => $taxResult['igst_total'],
            'gst_total'             => $taxResult['tax_total'],
            'grand_total'           => $taxResult['grand_total'],
            'credit_summary'        => [
                'credit_limit'                 => Money::toDecimal($creditLimitPaise),
                'current_outstanding'          => Money::toDecimal($currentOutstandingPaise),
                'available_credit'             => Money::toDecimal($availableCreditPaise),
                'order_amount'                 => $taxResult['grand_total'],
                'projected_outstanding'        => Money::toDecimal($projectedOutstandingPaise),
                'credit_available_after_order' => Money::toDecimal($postOrderAvailablePaise),
                'is_allowed'                   => $creditCheck['allowed'],
                'action'                       => $creditCheck['action'],
                'reason'                       => $creditCheck['reason'],
            ],
        ]);
    }

    private function checkScope(TenantContext $ctx, array $order): void
    {
        if ($ctx->isPartyBound() && ($order['party_ref'] ?? null) !== $ctx->partyRef) throw new NotFoundException('ORDER_NOT_FOUND', 'Order not found.');
        $territory = $this->parties->findTerritoryRefs($ctx->requireFranchise(), (string)$order['party_ref'])[0] ?? null;
        $this->authorization->requireRecordScope($ctx, 'orders', $order['sales_user_ref'] ?? null, $territory, $ctx->franchiseRef);
    }
}
