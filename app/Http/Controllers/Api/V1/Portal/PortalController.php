<?php
declare(strict_types=1);
namespace App\Http\Controllers\Api\V1\Portal;

use App\Core\{Request, Response, Container, TenantContext, Validation};
use App\Core\Exceptions\{ForbiddenException, NotFoundException, ValidationException};
use App\Domain\Orders\OrderService;
use App\Domain\Pricing\PriceResolver;
use App\Domain\Schemes\SchemeCalculator;
use App\Repositories\Contracts\{
    OrderRepositoryInterface,
    PartyRepositoryInterface,
    ProductRepositoryInterface,
    InvoiceRepositoryInterface,
    DispatchRepositoryInterface
};

final class PortalController
{
    public function __construct(
        private OrderRepositoryInterface $orderRepo,
        private PartyRepositoryInterface $partyRepo,
        private ProductRepositoryInterface $productRepo,
        private InvoiceRepositoryInterface $invoiceRepo,
        private DispatchRepositoryInterface $dispatchRepo,
        private OrderService $orderService,
        private PriceResolver $priceResolver,
        private SchemeCalculator $schemeCalculator,
    ) {}

    private function getCtx(): TenantContext
    {
        /** @var TenantContext $ctx */
        $ctx = Container::getInstance()->make(TenantContext::class);
        if (!$ctx->isSuperAdmin() && ($ctx->role !== 'DISTRIBUTOR' || empty($ctx->partyRef))) {
            throw new ForbiddenException('PORTAL_ACCESS_DENIED', 'Distributor portal credentials required.');
        }
        return $ctx;
    }

    public function profile(Request $r): Response
    {
        $ctx = $this->getCtx();
        $party = $this->partyRepo->findByRef($ctx->requireFranchise(), $ctx->partyRef);
        if (!$party) {
            throw new NotFoundException('PARTY_NOT_FOUND', 'Distributor profile not found.');
        }

        return Response::json(200, $party);
    }

    public function updateProfile(Request $r): Response
    {
        $ctx = $this->getCtx();
        $franchiseRef = $ctx->requireFranchise();
        $partyRef = $ctx->partyRef;

        // Allow updating only shipping address and contact info
        $clean = Validation::validate($r->all(), [
            'shipping_address' => 'string',
            'mobile'           => 'string',
            'contact_name'     => 'string',
        ]);

        $updateData = array_filter([
            'shipping_address' => $clean['shipping_address'] ?? null,
            'mobile'           => $clean['mobile'] ?? null,
            'contact_name'     => $clean['contact_name'] ?? null,
            'updated_by_ref'   => $ctx->userRef
        ], fn($v) => $v !== null);

        $this->partyRepo->update($franchiseRef, $partyRef, $updateData);
        $updated = $this->partyRepo->findByRef($franchiseRef, $partyRef);

        return Response::json(200, $updated);
    }

    public function catalogue(Request $r): Response
    {
        $ctx = $this->getCtx();
        $franchiseRef = $ctx->requireFranchise();
        $party = $this->partyRepo->findByRef($franchiseRef, $ctx->partyRef);
        $tierRef = $party['tier_ref'] ?? null;
        $date = date('Y-m-d');

        $products = $this->productRepo->listActive($franchiseRef);
        $catalogue = [];

        foreach ($products as $p) {
            try {
                $price = $this->priceResolver->resolve($franchiseRef, $p['product_ref'], $ctx->partyRef, $tierRef, $date);
                $rate = $price['rate'];
                $rateSource = $price['rate_source'];
            } catch (\Throwable $e) {
                $rate = (float)$p['franchise_rate'];
                $rateSource = 'DEFAULT';
            }

            $catalogue[] = [
                'product_ref'       => $p['product_ref'],
                'product_name'      => $p['product_name'],
                'sku'               => $p['sku'],
                'composition'       => $p['composition'] ?? null,
                'packing'           => $p['packing'] ?? null,
                'mrp'               => (float)$p['mrp'],
                'franchise_rate'    => $rate,
                'rate_source'       => $rateSource,
                'gst_rate'          => (float)($p['gst_rate'] ?? 0),
                'hsn_code'          => $p['hsn_code'] ?? null,
                'category_ref'      => $p['category_ref'] ?? null
            ];
        }

        return Response::json(200, $catalogue);
    }

    public function calculateCart(Request $r): Response
    {
        $ctx = $this->getCtx();
        $franchiseRef = $ctx->requireFranchise();
        $party = $this->partyRepo->findByRef($franchiseRef, $ctx->partyRef);
        $tierRef = $party['tier_ref'] ?? null;
        $date = date('Y-m-d');

        $clean = Validation::validate($r->all(), [
            'items' => 'required|array',
        ]);

        $items = $clean['items'];
        $calcItems = [];
        $subtotal = 0.0;
        $gstTotal = 0.0;

        foreach ($items as $item) {
            $prodRef = $item['product_ref'] ?? '';
            $qty = (int)($item['qty'] ?? $item['paid_qty'] ?? 0);
            if ($qty <= 0) continue;

            $prod = $this->productRepo->findByRef($franchiseRef, $prodRef);
            if (!$prod || $prod['status'] !== 'ACTIVE') continue;

            $price = $this->priceResolver->resolve($franchiseRef, $prodRef, $ctx->partyRef, $tierRef, $date);
            $unitRate = (float)$price['rate'];

            $scheme = $this->schemeCalculator->calculate($franchiseRef, $tierRef, $prodRef, $qty, $date);
            $freeQty = $scheme['free_qty'] ?? 0;

            $lineTotal = round($unitRate * $qty, 2);
            $gstRate = (float)$prod['gst_rate'];
            $lineGst = round($lineTotal * ($gstRate / 100), 2);

            $subtotal += $lineTotal;
            $gstTotal += $lineGst;

            $calcItems[] = [
                'product_ref'  => $prodRef,
                'product_name' => $prod['product_name'],
                'sku'          => $prod['sku'],
                'paid_qty'     => $qty,
                'free_qty'     => $freeQty,
                'unit_rate'    => $unitRate,
                'rate_source'  => $price['rate_source'],
                'gst_rate'     => $gstRate,
                'gst_amount'   => $lineGst,
                'line_total'   => $lineTotal,
                'scheme_name'  => $scheme['scheme_name'] ?? null
            ];
        }

        $grandTotal = round($subtotal + $gstTotal, 2);

        return Response::json(200, [
            'items'       => $calcItems,
            'subtotal'    => $subtotal,
            'gst_total'   => $gstTotal,
            'grand_total' => $grandTotal
        ]);
    }

    public function listOrders(Request $r): Response
    {
        $ctx = $this->getCtx();
        $franchiseRef = $ctx->requireFranchise();
        $page = (int)$r->query('page', 1);
        $perPage = min((int)$r->query('per_page', 20), 100);

        $res = $this->orderRepo->list($franchiseRef, [
            'status' => $r->query('status')
        ], $page, $perPage, $ctx->partyRef);

        return Response::json(200, $res['data'], [
            'total' => $res['total'],
            'page' => $res['page'],
            'per_page' => $res['per_page'],
        ]);
    }

    public function showOrder(Request $r, string $ref): Response
    {
        $ctx = $this->getCtx();
        $franchiseRef = $ctx->requireFranchise();
        $order = $this->orderRepo->findByRef($franchiseRef, $ref);

        if (!$order || $order['party_ref'] !== $ctx->partyRef) {
            throw new NotFoundException('ORDER_NOT_FOUND', 'Order not found.');
        }

        $items = $this->orderRepo->getItems($franchiseRef, $ref);
        $order['items'] = $items;

        return Response::json(200, $order);
    }

    public function placeOrder(Request $r): Response
    {
        $ctx = $this->getCtx();
        $franchiseRef = $ctx->requireFranchise();
        $partyRef = $ctx->partyRef;

        $clean = Validation::validate($r->all(), [
            'client_order_ref' => 'required|string',
            'items'            => 'required|array',
        ]);

        $order = $this->orderService->createOrder(
            orgRef: $ctx->orgRef,
            franchiseRef: $franchiseRef,
            partyRef: $partyRef,
            clientOrderRef: (string)$clean['client_order_ref'],
            channel: 'PORTAL',
            rawItems: (array)$clean['items'],
            salesUserRef: null,
            shippingAddress: $r->input('shipping_address'),
            shippingPincode: $r->input('shipping_pincode'),
            remarks: $r->input('remarks'),
            actorRef: $ctx->userRef
        );

        return Response::json(201, $order);
    }

    public function cancelOrder(Request $r, string $ref): Response
    {
        $ctx = $this->getCtx();
        $franchiseRef = $ctx->requireFranchise();
        $order = $this->orderRepo->findByRef($franchiseRef, $ref);

        if (!$order || $order['party_ref'] !== $ctx->partyRef) {
            throw new NotFoundException('ORDER_NOT_FOUND', 'Order not found.');
        }

        if ($order['status'] !== 'DRAFT') {
            throw new ValidationException('CANNOT_CANCEL', 'Order can only be cancelled while in DRAFT status.');
        }

        $reason = (string)$r->input('reason', 'Cancelled by distributor partner via Portal');
        $this->orderService->cancelOrder($franchiseRef, $ref, $reason, $ctx->userRef);

        return Response::json(200, [
            'order_ref' => $ref,
            'status'    => 'CANCELLED'
        ]);
    }

    public function listInvoices(Request $r): Response
    {
        $ctx = $this->getCtx();
        $franchiseRef = $ctx->requireFranchise();
        $page = (int)$r->query('page', 1);
        $perPage = min((int)$r->query('per_page', 20), 100);

        $res = $this->invoiceRepo->list($franchiseRef, [
            'party_ref' => $ctx->partyRef,
            'status'    => $r->query('status')
        ], $page, $perPage);

        return Response::json(200, $res['data'], [
            'total' => $res['total'],
            'page' => $res['page'],
            'per_page' => $res['per_page'],
        ]);
    }

    public function listDispatches(Request $r): Response
    {
        $ctx = $this->getCtx();
        $franchiseRef = $ctx->requireFranchise();
        $page = (int)$r->query('page', 1);
        $perPage = min((int)$r->query('per_page', 20), 100);

        $res = $this->dispatchRepo->list($franchiseRef, [
            'party_ref' => $ctx->partyRef
        ], $page, $perPage);

        return Response::json(200, $res['data'], [
            'total' => $res['total'],
            'page' => $res['page'],
            'per_page' => $res['per_page'],
        ]);
    }

    public function outstanding(Request $r): Response
    {
        $ctx = $this->getCtx();
        $franchiseRef = $ctx->requireFranchise();

        $summary = $this->partyRepo->getLedgerSummary($franchiseRef, $ctx->partyRef);

        return Response::json(200, $summary);
    }
}
