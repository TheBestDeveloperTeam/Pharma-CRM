<?php
declare(strict_types=1);
namespace App\Http\Controllers\Api\V1\Admin;

use App\Core\Request;
use App\Core\Response;
use App\Core\Validation;
use App\Core\TenantContext;
use App\Core\Exceptions\NotFoundException;
use App\Domain\Orders\OrderService;
use App\Repositories\Contracts\OrderRepositoryInterface;

final class OrdersController
{
    public function __construct(
        private OrderRepositoryInterface $orderRepo,
        private OrderService $orderService,
    ) {}

    public function index(Request $r): Response
    {
        $ctx = TenantContext::get();
        $franchiseRef = $ctx->franchiseRef;
        $page = (int)$r->query('page', 1);
        $perPage = min((int)$r->query('per_page', 20), 100);

        $salesUser = ($ctx->role === 'SALES') ? $ctx->userRef : null;
        $partyRef = ($ctx->role === 'DISTRIBUTOR') ? $ctx->partyRef : null;

        $filters = [
            'status'          => $r->query('status'),
            'search'          => $r->query('search'),
            'sales_user_ref'  => $salesUser,
        ];

        $res = $this->orderRepo->list($franchiseRef, $filters, $page, $perPage, $partyRef);
        return Response::json(['data' => $res]);
    }

    public function show(Request $r, string $ref): Response
    {
        $ctx = TenantContext::get();
        $order = $this->orderRepo->findByRef($ctx->franchiseRef, $ref);
        if (!$order) {
            throw new NotFoundException('ORDER_NOT_FOUND', 'Order not found.');
        }

        $items = $this->orderRepo->getItems($ctx->franchiseRef, $ref);
        $order['items'] = $items;

        return Response::json(['data' => $order]);
    }

    public function store(Request $r): Response
    {
        $ctx = TenantContext::get();
        $clean = Validation::validate($r->all(), [
            'party_ref'        => 'required|string',
            'client_order_ref' => 'required|string',
            'channel'          => 'required|string',
            'items'            => 'required|array',
        ]);

        $res = $this->orderService->createOrder(
            $ctx->orgRef,
            $ctx->franchiseRef,
            $clean['party_ref'],
            $clean['client_order_ref'],
            $clean['channel'],
            $clean['items'],
            $r->input('sales_user_ref') ?? ($ctx->role === 'SALES' ? $ctx->userRef : null),
            $r->input('shipping_address'),
            $r->input('shipping_pincode'),
            $r->input('remarks'),
            $ctx->userRef
        );

        return Response::json(['data' => $res], 201);
    }

    public function confirm(Request $r, string $ref): Response
    {
        $ctx = TenantContext::get();
        $res = $this->orderService->confirmOrder($ctx->orgRef, $ctx->franchiseRef, $ref, $ctx->userRef);
        return Response::json(['data' => $res]);
    }

    public function cancel(Request $r, string $ref): Response
    {
        $ctx = TenantContext::get();
        $clean = Validation::validate($r->all(), [
            'reason' => 'required|string',
        ]);

        $this->orderService->cancelOrder($ctx->orgRef, $ctx->franchiseRef, $ref, $ctx->userRef, $clean['reason']);
        return Response::json(['data' => ['order_ref' => $ref, 'status' => 'CANCELLED']]);
    }
}
