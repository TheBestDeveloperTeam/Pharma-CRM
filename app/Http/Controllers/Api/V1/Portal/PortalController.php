<?php
declare(strict_types=1);
namespace App\Http\Controllers\Api\V1\Portal;

use App\Core\{Request, Response, Container, TenantContext, Validation, RefGenerator};
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
    private \PDO $pdo;

    public function __construct(
        private OrderRepositoryInterface $orderRepo,
        private PartyRepositoryInterface $partyRepo,
        private ProductRepositoryInterface $productRepo,
        private InvoiceRepositoryInterface $invoiceRepo,
        private DispatchRepositoryInterface $dispatchRepo,
        private OrderService $orderService,
        private PriceResolver $priceResolver,
        private SchemeCalculator $schemeCalculator,
        ?\PDO $pdo = null,
    ) {
        $this->pdo = $pdo ?? Container::getInstance()->make(\PDO::class);
    }

    /**
     * B1 — portal access = the user is bound to a party (data identity) AND
     * holds the portal.<action> permission; no longer users.role === 'DISTRIBUTOR'.
     * Super Admin keeps its platform bypass, exactly as before.
     */
    private function getCtx(string $action = 'view'): TenantContext
    {
        /** @var TenantContext $ctx */
        $ctx = Container::getInstance()->make(TenantContext::class);
        if ($ctx->isSuper()) return $ctx;
        if (!$ctx->isPartyBound() || !$ctx->can('portal', $action)) {
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
        $ctx = $this->getCtx('editProfile');
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
        $ctx = $this->getCtx('placeOrder');
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

        return Response::json(200, $res['data'], $res['meta']);
    }

    public function showOrder(Request $r, ?string $ref = null): Response
    {
        $ctx = $this->getCtx();
        $franchiseRef = $ctx->requireFranchise();
        $ref = $ref ?? (string)$r->param('ref');
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
        $ctx = $this->getCtx('placeOrder');
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

    public function cancelOrder(Request $r, ?string $ref = null): Response
    {
        $ctx = $this->getCtx('placeOrder');
        $franchiseRef = $ctx->requireFranchise();
        $ref = $ref ?? (string)$r->param('ref');
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
            'total_pages' => $res['per_page'] > 0 ? (int)ceil($res['total'] / $res['per_page']) : 0,
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
            'total_pages' => $res['per_page'] > 0 ? (int)ceil($res['total'] / $res['per_page']) : 0,
        ]);
    }

    public function outstanding(Request $r): Response
    {
        $ctx = $this->getCtx();
        $franchiseRef = $ctx->requireFranchise();

        $summary = $this->partyRepo->getLedgerSummary($franchiseRef, $ctx->partyRef);

        return Response::json(200, $summary);
    }

    /**
     * PRT-001: Portal dashboard (order stats, ledger, recent dispatches, pending invoices)
     */
    public function dashboard(Request $r): Response
    {
        $ctx = $this->getCtx();
        $franchiseRef = $ctx->requireFranchise();
        $partyRef = $ctx->partyRef;

        // 1. Order stats
        $stmt = $this->pdo->prepare("SELECT status, COUNT(*) as count, COALESCE(SUM(grand_total), 0) as total_value FROM orders WHERE franchise_ref = :f AND party_ref = :p GROUP BY status");
        $stmt->execute([':f' => $franchiseRef, ':p' => $partyRef]);
        $orderStats = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        // 2. Ledger summary
        $ledgerSummary = $this->partyRepo->getLedgerSummary($franchiseRef, $partyRef);

        // 3. Recent dispatches
        $stmt = $this->pdo->prepare("SELECT d.dispatch_ref, d.dispatch_no, d.lr_number, d.status, d.dispatch_date, d.delivered_at, d.boxes, tr.transporter_name FROM dispatches d JOIN invoices i ON i.franchise_ref = d.franchise_ref AND i.invoice_ref = d.invoice_ref LEFT JOIN transporters tr ON tr.franchise_ref = d.franchise_ref AND tr.transporter_ref = d.transporter_ref WHERE d.franchise_ref = :f AND i.party_ref = :p ORDER BY d.created_at DESC LIMIT 5");
        $stmt->execute([':f' => $franchiseRef, ':p' => $partyRef]);
        $recentDispatches = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        // 4. Invoices pending payment
        $stmt = $this->pdo->prepare("SELECT invoice_ref, invoice_no, invoice_date, due_date, grand_total, paid_total, (grand_total - paid_total) as balance_due, status FROM invoices WHERE franchise_ref = :f AND party_ref = :p AND status = 'POSTED' AND (grand_total - paid_total) > 0 ORDER BY invoice_date ASC LIMIT 5");
        $stmt->execute([':f' => $franchiseRef, ':p' => $partyRef]);
        $pendingInvoices = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        return Response::json(200, [
            'orders'            => $orderStats,
            'ledger'            => $ledgerSummary,
            'recent_dispatches' => $recentDispatches,
            'pending_invoices'  => $pendingInvoices,
        ]);
    }

    /**
     * PRT-002: Order status tracking timeline
     */
    public function orderTimeline(Request $r, ?string $ref = null): Response
    {
        $ctx = $this->getCtx();
        $franchiseRef = $ctx->requireFranchise();
        $ref = $ref ?? (string)$r->param('ref');

        $order = $this->orderRepo->findByRef($franchiseRef, $ref);
        if (!$order || $order['party_ref'] !== $ctx->partyRef) {
            throw new NotFoundException('ORDER_NOT_FOUND', 'Order not found.');
        }

        $stmt = $this->pdo->prepare("SELECT from_status, to_status, actor_ref, reason, created_at FROM order_status_history WHERE franchise_ref = :f AND order_ref = :r ORDER BY id ASC");
        $stmt->execute([':f' => $franchiseRef, ':r' => $ref]);
        $timeline = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        return Response::json(200, $timeline);
    }

    /**
     * PRT-003: Invoice detail view
     */
    public function showInvoice(Request $r, ?string $ref = null): Response
    {
        $ctx = $this->getCtx();
        $franchiseRef = $ctx->requireFranchise();
        $ref = $ref ?? (string)$r->param('ref');

        $inv = $this->invoiceRepo->findByRef($franchiseRef, $ref);
        if (!$inv || $inv['party_ref'] !== $ctx->partyRef) {
            throw new NotFoundException('INVOICE_NOT_FOUND', 'Invoice not found.');
        }

        $items = $this->invoiceRepo->getItems($franchiseRef, $ref);
        $inv['items'] = $items;

        $stmt = $this->pdo->prepare("SELECT a.allocation_ref, a.payment_ref, a.allocated_amount, a.created_at, p.payment_no, p.mode FROM payment_allocations a JOIN payments p ON p.franchise_ref = a.franchise_ref AND p.payment_ref = a.payment_ref WHERE a.franchise_ref = :f AND a.invoice_ref = :i AND a.status = 'ACTIVE'");
        $stmt->execute([':f' => $franchiseRef, ':i' => $ref]);
        $inv['payments'] = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        return Response::json(200, $inv);
    }

    /**
     * PRT-004: Download invoice statement
     */
    public function downloadInvoice(Request $r, ?string $ref = null): Response
    {
        $ctx = $this->getCtx();
        $franchiseRef = $ctx->requireFranchise();
        $ref = $ref ?? (string)$r->param('ref');

        $inv = $this->invoiceRepo->findByRef($franchiseRef, $ref);
        if (!$inv || $inv['party_ref'] !== $ctx->partyRef) {
            throw new NotFoundException('INVOICE_NOT_FOUND', 'Invoice not found.');
        }

        $items = $this->invoiceRepo->getItems($franchiseRef, $ref);
        $party = $this->partyRepo->findByRef($franchiseRef, $ctx->partyRef);

        return Response::json(200, [
            'document_type' => 'TAX_INVOICE',
            'invoice'       => $inv,
            'items'         => $items,
            'party'         => $party,
            'generated_at'  => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * PRT-005: Dispatch detail (transporter, LR, tracking)
     */
    public function showDispatch(Request $r, ?string $ref = null): Response
    {
        $ctx = $this->getCtx();
        $franchiseRef = $ctx->requireFranchise();
        $ref = $ref ?? (string)$r->param('ref');

        $stmt = $this->pdo->prepare("SELECT d.*, tr.transporter_name, tr.tracking_url_template, i.invoice_no, i.order_ref, i.party_ref FROM dispatches d JOIN invoices i ON i.franchise_ref = d.franchise_ref AND i.invoice_ref = d.invoice_ref LEFT JOIN transporters tr ON tr.franchise_ref = d.franchise_ref AND tr.transporter_ref = d.transporter_ref WHERE d.franchise_ref = :f AND d.dispatch_ref = :r LIMIT 1");
        $stmt->execute([':f' => $franchiseRef, ':r' => $ref]);
        $dsp = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$dsp || $dsp['party_ref'] !== $ctx->partyRef) {
            throw new NotFoundException('DISPATCH_NOT_FOUND', 'Dispatch record not found.');
        }

        $stmt2 = $this->pdo->prepare("SELECT from_status, to_status, actor_ref, reason, created_at FROM dispatch_status_history WHERE franchise_ref = :f AND dispatch_ref = :r ORDER BY id ASC");
        $stmt2->execute([':f' => $franchiseRef, ':r' => $ref]);
        $dsp['history'] = $stmt2->fetchAll(\PDO::FETCH_ASSOC);

        return Response::json(200, $dsp);
    }

    /**
     * PRT-006: Payment history
     */
    public function payments(Request $r): Response
    {
        $ctx = $this->getCtx();
        $franchiseRef = $ctx->requireFranchise();
        $partyRef = $ctx->partyRef;

        $page = max(1, (int)$r->query('page', 1));
        $perPage = min(100, max(1, (int)$r->query('per_page', 20)));
        $offset = ($page - 1) * $perPage;

        $stmtCount = $this->pdo->prepare("SELECT COUNT(*) FROM payments WHERE franchise_ref = :f AND party_ref = :p");
        $stmtCount->execute([':f' => $franchiseRef, ':p' => $partyRef]);
        $total = (int)$stmtCount->fetchColumn();

        $stmt = $this->pdo->prepare("SELECT payment_ref, payment_no, payment_date, amount, allocated_amount, mode, reference_no, bank_name, cheque_number, cheque_date, status, remarks, created_at FROM payments WHERE franchise_ref = :f AND party_ref = :p ORDER BY payment_date DESC LIMIT :lim OFFSET :off");
        $stmt->bindValue(':f', $franchiseRef);
        $stmt->bindValue(':p', $partyRef);
        $stmt->bindValue(':lim', $perPage, \PDO::PARAM_INT);
        $stmt->bindValue(':off', $offset, \PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        return Response::json(200, $rows, [
            'total'       => $total,
            'page'        => $page,
            'per_page'    => $perPage,
            'total_pages' => $perPage > 0 ? (int)ceil($total / $perPage) : 0,
        ]);
    }

    /**
     * PRT-007: Outstanding ageing for self
     */
    public function outstandingAgeing(Request $r): Response
    {
        $ctx = $this->getCtx();
        $franchiseRef = $ctx->requireFranchise();
        $partyRef = $ctx->partyRef;

        $stmt = $this->pdo->prepare("SELECT invoice_ref, invoice_no, invoice_date, due_date, grand_total, paid_total, (grand_total - paid_total) as balance, DATEDIFF(CURDATE(), COALESCE(due_date, invoice_date)) as overdue_days FROM invoices WHERE franchise_ref = :f AND party_ref = :p AND status = 'POSTED' AND (grand_total - paid_total) > 0");
        $stmt->execute([':f' => $franchiseRef, ':p' => $partyRef]);
        $invoices = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $buckets = ['current' => 0.0, '1_30' => 0.0, '31_60' => 0.0, '61_90' => 0.0, '90_plus' => 0.0];
        $total = 0.0;

        foreach ($invoices as $inv) {
            $bal = (float)$inv['balance'];
            $days = (int)$inv['overdue_days'];
            $total += $bal;

            if ($days <= 0) {
                $buckets['current'] += $bal;
            } elseif ($days <= 30) {
                $buckets['1_30'] += $bal;
            } elseif ($days <= 60) {
                $buckets['31_60'] += $bal;
            } elseif ($days <= 90) {
                $buckets['61_90'] += $bal;
            } else {
                $buckets['90_plus'] += $bal;
            }
        }

        return Response::json(200, [
            'total_outstanding' => round($total, 2),
            'buckets'           => array_map(fn($v) => round($v, 2), $buckets),
            'invoices'          => $invoices,
        ]);
    }

    /**
     * PRT-008: Applicable offers & schemes with validity
     */
    public function schemes(Request $r): Response
    {
        $ctx = $this->getCtx();
        $franchiseRef = $ctx->requireFranchise();
        $today = date('Y-m-d');

        $stmt = $this->pdo->prepare("SELECT s.scheme_ref, s.scheme_name, s.scheme_type, s.start_date, s.end_date, s.is_stackable, s.description FROM schemes s WHERE s.franchise_ref = :f AND s.status = 'ACTIVE' AND s.start_date <= :d1 AND (s.end_date IS NULL OR s.end_date >= :d2) ORDER BY s.start_date DESC");
        $stmt->execute([':f' => $franchiseRef, ':d1' => $today, ':d2' => $today]);
        $schemes = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        foreach ($schemes as &$scheme) {
            $stmtRules = $this->pdo->prepare("SELECT sr.rule_ref, sr.product_ref, sr.min_qty, sr.free_product_ref, sr.free_qty, sr.discount_percent, p.product_name, fp.product_name as free_product_name FROM scheme_rules sr LEFT JOIN products p ON p.franchise_ref = sr.franchise_ref AND p.product_ref = sr.product_ref LEFT JOIN products fp ON fp.franchise_ref = sr.franchise_ref AND fp.product_ref = sr.free_product_ref WHERE sr.franchise_ref = :f AND sr.scheme_ref = :s");
            $stmtRules->execute([':f' => $franchiseRef, ':s' => $scheme['scheme_ref']]);
            $scheme['rules'] = $stmtRules->fetchAll(\PDO::FETCH_ASSOC);
        }

        return Response::json(200, $schemes);
    }

    /**
     * PRT-009: Support/contact info
     */
    public function support(Request $r): Response
    {
        $ctx = $this->getCtx();
        $franchiseRef = $ctx->requireFranchise();

        $stmt = $this->pdo->prepare("SELECT franchise_name, franchise_code, address, gstin, drug_license_no, status FROM franchises WHERE franchise_ref = :f LIMIT 1");
        $stmt->execute([':f' => $franchiseRef]);
        $frn = $stmt->fetch(\PDO::FETCH_ASSOC);

        return Response::json(200, [
            'franchise_name' => $frn['franchise_name'] ?? 'Pharma HQ',
            'support_email'  => 'support@' . strtolower($frn['franchise_code'] ?? 'pharma') . '.com',
            'support_phone'  => '1800-PHARMA-CRM',
            'address'        => $frn['address'] ?? null,
            'gstin'          => $frn['gstin'] ?? null,
            'hours'          => 'Monday to Saturday, 9:00 AM - 6:00 PM IST',
        ]);
    }

    /**
     * PRT-010: Portal notifications
     */
    public function notifications(Request $r): Response
    {
        $ctx = $this->getCtx();
        $franchiseRef = $ctx->requireFranchise();
        $limit = min(50, max(1, (int)$r->query('limit', 20)));

        $stmt = $this->pdo->prepare("SELECT notification_ref, event_type, entity_type, entity_ref, payload_json, status, created_at, read_at FROM notifications WHERE franchise_ref = :f AND (party_ref = :p OR party_ref IS NULL) AND channel = 'INAPP' ORDER BY created_at DESC LIMIT :lim");
        $stmt->bindValue(':f', $franchiseRef);
        $stmt->bindValue(':p', $ctx->partyRef);
        $stmt->bindValue(':lim', $limit, \PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        foreach ($rows as &$row) {
            $row['payload'] = json_decode($row['payload_json'] ?? '{}', true);
            unset($row['payload_json']);
        }

        return Response::json(200, $rows);
    }

    /**
     * PRT-011: Mark notification read
     */
    public function markNotificationRead(Request $r, ?string $ref = null): Response
    {
        $ctx = $this->getCtx();
        $franchiseRef = $ctx->requireFranchise();
        $ref = $ref ?? (string)$r->param('ref');

        $stmt = $this->pdo->prepare("UPDATE notifications SET status = 'READ', read_at = NOW() WHERE franchise_ref = :f AND notification_ref = :r AND (party_ref = :p OR party_ref IS NULL)");
        $stmt->execute([':f' => $franchiseRef, ':r' => $ref, ':p' => $ctx->partyRef]);

        return Response::json(200, ['notification_ref' => $ref, 'read' => true]);
    }

    /**
     * PRT-012: Manage shipping addresses - List
     */
    public function listShippingAddresses(Request $r): Response
    {
        $ctx = $this->getCtx();
        $franchiseRef = $ctx->requireFranchise();
        $partyRef = $ctx->partyRef;

        $party = $this->partyRepo->findByRef($franchiseRef, $partyRef);
        $addresses = [];

        if (!empty($party['shipping_address'])) {
            $addresses[] = [
                'address_ref' => 'ADDR-PRIMARY',
                'label'       => 'Default / Primary Warehouse',
                'address'     => $party['shipping_address'],
                'pincode'     => $party['pincode'] ?? '',
                'city_ref'    => $party['city_ref'] ?? null,
                'state_ref'   => $party['state_ref'] ?? null,
                'is_primary'  => true,
            ];
        }

        $stmt = $this->pdo->prepare("SELECT master_value_ref as address_ref, code as label, label as address_data FROM catalog_master_values WHERE franchise_ref = :f AND master_key = 'party_shipping_addresses' AND org_ref = :p AND status = 'ACTIVE'");
        $stmt->execute([':f' => $franchiseRef, ':p' => $partyRef]);
        while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            $data = json_decode($row['address_data'] ?? '{}', true) ?: [];
            $addresses[] = array_merge([
                'address_ref' => $row['address_ref'],
                'label'       => $row['label'],
                'is_primary'  => false,
            ], $data);
        }

        return Response::json(200, $addresses);
    }

    /**
     * PRT-013: Add shipping address
     */
    public function addShippingAddress(Request $r): Response
    {
        $ctx = $this->getCtx('editProfile');
        $franchiseRef = $ctx->requireFranchise();
        $partyRef = $ctx->partyRef;

        $clean = Validation::validate($r->all(), [
            'label'   => 'required|string',
            'address' => 'required|string',
            'pincode' => 'string',
        ]);

        $addrRef = RefGenerator::generate('ADR');
        $addrData = json_encode([
            'address'   => $clean['address'],
            'pincode'   => $clean['pincode'] ?? null,
            'city_ref'  => $r->input('city_ref'),
            'state_ref' => $r->input('state_ref'),
        ]);

        $stmt = $this->pdo->prepare("INSERT INTO catalog_master_values (master_value_ref, org_ref, franchise_ref, master_key, code, label, sort_order, status, created_by_ref) VALUES (:ref, :org, :f, 'party_shipping_addresses', :label, :data, 0, 'ACTIVE', :actor)");
        $stmt->execute([
            ':ref'   => $addrRef,
            ':org'   => $partyRef,
            ':f'     => $franchiseRef,
            ':label' => $clean['label'],
            ':data'  => $addrData,
            ':actor' => $ctx->userRef ?? 'PORTAL',
        ]);

        return Response::json(201, [
            'address_ref' => $addrRef,
            'label'       => $clean['label'],
            'address'     => $clean['address'],
            'pincode'     => $clean['pincode'] ?? null,
            'is_primary'  => false,
        ]);
    }

    /**
     * PRT-014: Update shipping address
     */
    public function updateShippingAddress(Request $r, ?string $ref = null): Response
    {
        $ctx = $this->getCtx('editProfile');
        $franchiseRef = $ctx->requireFranchise();
        $ref = $ref ?? (string)$r->param('ref');

        if ($ref === 'ADDR-PRIMARY') {
            $this->partyRepo->update($franchiseRef, $ctx->partyRef, [
                'shipping_address' => $r->input('address'),
                'pincode'          => $r->input('pincode'),
            ]);
            return Response::json(200, ['address_ref' => 'ADDR-PRIMARY', 'updated' => true]);
        }

        $addrData = json_encode([
            'address'   => $r->input('address'),
            'pincode'   => $r->input('pincode'),
            'city_ref'  => $r->input('city_ref'),
            'state_ref' => $r->input('state_ref'),
        ]);

        $stmt = $this->pdo->prepare("UPDATE catalog_master_values SET label = :data, code = COALESCE(:label, code) WHERE franchise_ref = :f AND master_value_ref = :r AND master_key = 'party_shipping_addresses'");
        $stmt->execute([
            ':data'  => $addrData,
            ':label' => $r->input('label'),
            ':f'     => $franchiseRef,
            ':r'     => $ref,
        ]);

        return Response::json(200, ['address_ref' => $ref, 'updated' => true]);
    }

    /**
     * PRT-015: Delete shipping address
     */
    public function deleteShippingAddress(Request $r, ?string $ref = null): Response
    {
        $ctx = $this->getCtx('editProfile');
        $franchiseRef = $ctx->requireFranchise();
        $ref = $ref ?? (string)$r->param('ref');

        $stmt = $this->pdo->prepare("UPDATE catalog_master_values SET status = 'INACTIVE' WHERE franchise_ref = :f AND master_value_ref = :r AND master_key = 'party_shipping_addresses'");
        $stmt->execute([':f' => $franchiseRef, ':r' => $ref]);

        return Response::json(200, ['address_ref' => $ref, 'deleted' => true]);
    }

    /**
     * PRT-016: List distributor team
     */
    public function listTeam(Request $r): Response
    {
        $ctx = $this->getCtx();
        $franchiseRef = $ctx->requireFranchise();
        $partyRef = $ctx->partyRef;

        $stmt = $this->pdo->prepare("SELECT user_ref, email, mobile, full_name, role, status, created_at, updated_at FROM users WHERE franchise_ref = :f AND party_ref = :p AND status != 'DELETED' ORDER BY full_name ASC");
        $stmt->execute([':f' => $franchiseRef, ':p' => $partyRef]);
        $team = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        return Response::json(200, $team);
    }

    /**
     * PRT-017: Create distributor team user
     */
    public function createTeam(Request $r): Response
    {
        $ctx = $this->getCtx('editProfile');
        $franchiseRef = $ctx->requireFranchise();
        $partyRef = $ctx->partyRef;

        $clean = Validation::validate($r->all(), [
            'full_name' => 'required|string',
            'email'     => 'required|email',
            'mobile'    => 'required|string',
        ]);

        $userRef = RefGenerator::generate('USR');
        $tempPassword = password_hash($r->input('password') ?: bin2hex(random_bytes(6)), PASSWORD_DEFAULT);

        $stmt = $this->pdo->prepare("INSERT INTO users (user_ref, org_ref, franchise_ref, party_ref, email, mobile, full_name, role, password_hash, status, created_by_ref) VALUES (:ref, :org, :f, :p, :email, :mobile, :name, 'DISTRIBUTOR', :pwd, 'ACTIVE', :creator)");
        $stmt->execute([
            ':ref'     => $userRef,
            ':org'     => $ctx->orgRef,
            ':f'       => $franchiseRef,
            ':p'       => $partyRef,
            ':email'   => strtolower(trim((string)$clean['email'])),
            ':mobile'  => trim((string)$clean['mobile']),
            ':name'    => trim((string)$clean['full_name']),
            ':pwd'     => $tempPassword,
            ':creator' => $ctx->userRef,
        ]);

        return Response::json(201, [
            'user_ref'  => $userRef,
            'full_name' => $clean['full_name'],
            'email'     => $clean['email'],
            'mobile'    => $clean['mobile'],
            'role'      => 'DISTRIBUTOR',
            'status'    => 'ACTIVE',
        ]);
    }

    /**
     * PRT-018: Show team user detail
     */
    public function showTeam(Request $r, ?string $ref = null): Response
    {
        $ctx = $this->getCtx();
        $franchiseRef = $ctx->requireFranchise();
        $ref = $ref ?? (string)$r->param('ref');

        $stmt = $this->pdo->prepare("SELECT user_ref, email, mobile, full_name, role, status, created_at, updated_at FROM users WHERE franchise_ref = :f AND party_ref = :p AND user_ref = :u LIMIT 1");
        $stmt->execute([':f' => $franchiseRef, ':p' => $ctx->partyRef, ':u' => $ref]);
        $user = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$user) {
            throw new NotFoundException('TEAM_USER_NOT_FOUND', 'Team member not found.');
        }

        return Response::json(200, $user);
    }

    /**
     * PRT-019: Update team user
     */
    public function updateTeam(Request $r, ?string $ref = null): Response
    {
        $ctx = $this->getCtx('editProfile');
        $franchiseRef = $ctx->requireFranchise();
        $ref = $ref ?? (string)$r->param('ref');

        $clean = Validation::validate($r->all(), [
            'full_name' => 'string',
            'mobile'    => 'string',
        ]);

        $updates = [];
        $params = [':f' => $franchiseRef, ':p' => $ctx->partyRef, ':u' => $ref];

        if (!empty($clean['full_name'])) {
            $updates[] = 'full_name = :name';
            $params[':name'] = $clean['full_name'];
        }
        if (!empty($clean['mobile'])) {
            $updates[] = 'mobile = :mob';
            $params[':mob'] = $clean['mobile'];
        }

        if (!empty($updates)) {
            $sql = "UPDATE users SET " . implode(', ', $updates) . " WHERE franchise_ref = :f AND party_ref = :p AND user_ref = :u";
            $this->pdo->prepare($sql)->execute($params);
        }

        return Response::json(200, ['user_ref' => $ref, 'updated' => true]);
    }

    /**
     * PRT-020: Activate team user
     */
    public function activateTeam(Request $r, ?string $ref = null): Response
    {
        $ctx = $this->getCtx('editProfile');
        $franchiseRef = $ctx->requireFranchise();
        $ref = $ref ?? (string)$r->param('ref');

        $stmt = $this->pdo->prepare("UPDATE users SET status = 'ACTIVE' WHERE franchise_ref = :f AND party_ref = :p AND user_ref = :u");
        $stmt->execute([':f' => $franchiseRef, ':p' => $ctx->partyRef, ':u' => $ref]);

        return Response::json(200, ['user_ref' => $ref, 'status' => 'ACTIVE']);
    }

    /**
     * PRT-021: Deactivate team user
     */
    public function deactivateTeam(Request $r, ?string $ref = null): Response
    {
        $ctx = $this->getCtx('editProfile');
        $franchiseRef = $ctx->requireFranchise();
        $ref = $ref ?? (string)$r->param('ref');

        $stmt = $this->pdo->prepare("UPDATE users SET status = 'SUSPENDED' WHERE franchise_ref = :f AND party_ref = :p AND user_ref = :u");
        $stmt->execute([':f' => $franchiseRef, ':p' => $ctx->partyRef, ':u' => $ref]);

        return Response::json(200, ['user_ref' => $ref, 'status' => 'SUSPENDED']);
    }

    /**
     * PRT-022: Assign beat to team user
     */
    public function assignBeat(Request $r, ?string $ref = null): Response
    {
        $ctx = $this->getCtx('editProfile');
        $franchiseRef = $ctx->requireFranchise();
        $ref = $ref ?? (string)$r->param('ref');

        $clean = Validation::validate($r->all(), [
            'beat' => 'required|string',
        ]);

        $stmt = $this->pdo->prepare("INSERT INTO catalog_master_values (master_value_ref, org_ref, franchise_ref, master_key, code, label, sort_order, status, created_by_ref) VALUES (:ref, :u, :f, 'user_assigned_beat', :beat, :time, 0, 'ACTIVE', :actor) ON DUPLICATE KEY UPDATE code = VALUES(code)");
        $stmt->execute([
            ':ref'   => RefGenerator::generate('CMV'),
            ':u'     => $ref,
            ':f'     => $franchiseRef,
            ':beat'  => $clean['beat'],
            ':time'  => date('Y-m-d H:i:s'),
            ':actor' => $ctx->userRef,
        ]);

        return Response::json(200, [
            'user_ref' => $ref,
            'beat'     => $clean['beat'],
            'assigned' => true,
        ]);
    }
}
