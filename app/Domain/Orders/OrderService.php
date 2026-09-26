<?php
declare(strict_types=1);
namespace App\Domain\Orders;

use App\Core\RefGenerator;
use App\Core\Database;
use App\Core\SequenceService;
use App\Core\Exceptions\ValidationException;
use App\Core\Exceptions\ConflictException;
use App\Support\Money;
use App\Domain\Pricing\PriceResolver;
use App\Domain\Schemes\SchemeCalculator;
use App\Domain\Territory\{TerritoryValidator, TerritoryResolver};
use App\Domain\Inventory\FefoAllocator;
use App\Repositories\Contracts\OrderRepositoryInterface;
use App\Repositories\Contracts\PartyRepositoryInterface;
use App\Repositories\Contracts\ProductRepositoryInterface;
use App\Domain\Parties\PartyCreditService;

final class OrderService
{
    public function __construct(
        private OrderRepositoryInterface $orderRepo,
        private PartyRepositoryInterface $partyRepo,
        private PartyCreditService $partyCreditService,
        private ProductRepositoryInterface $productRepo,
        private PriceResolver $priceResolver,
        private SchemeCalculator $schemeCalculator,
        private TerritoryResolver $territoryResolver,
        private TerritoryValidator $territoryValidator,
        private CreditRuleService $creditRuleService,
        private FefoAllocator $fefoAllocator,
        private SequenceService $sequenceService,
        private Database $db,
    ) {}

    public function createOrder(
        string $orgRef,
        string $franchiseRef,
        string $partyRef,
        string $clientOrderRef,
        string $channel,
        array $rawItems, // [['product_ref' => string, 'paid_qty' => int]]
        ?string $salesUserRef,
        ?string $shippingAddress,
        ?string $shippingPincode,
        ?string $remarks,
        string $actorRef
    ): array {
        // 1. Check duplicate client_order_ref per franchise
        $existing = $this->orderRepo->findByClientRef($franchiseRef, $clientOrderRef);
        if ($existing) {
            throw new ConflictException(
                'DUPLICATE_ORDER_CLIENT_REF',
                "Order with client reference '{$clientOrderRef}' already exists in this franchise."
            );
        }

        // 2. Validate party
        $party = $this->partyRepo->findByRef($franchiseRef, $partyRef);
        if (!$party || $party['status'] !== 'ACTIVE') {
            throw new ValidationException('INVALID_PARTY', 'Party is inactive or does not exist.');
        }

        // 3. Resolve Territory fact. Draft creation does not enforce unresolved policy.
        $targetPincode = $shippingPincode ?? $party['pincode'];
        $today = date('Y-m-d');

        $territoryStatus = 'OK';
        if ($targetPincode) {
            $terrCheck = $this->territoryResolver->resolve($franchiseRef, $partyRef, $targetPincode, null, $today);
            $territoryStatus = $terrCheck['status'] === TerritoryResolver::MATCHED ? 'OK' : $terrCheck['status'];
        }

        // 4. Price and calculate items using paise math
        $orderRef = RefGenerator::generate('ord');
        $orderNo = $this->sequenceService->next($franchiseRef, 'ORDER', date('Y-m-d'));

        $processedItems = [];
        $subtotalPaise = 0;
        $discountTotalPaise = 0;
        $gstTotalPaise = 0;
        $grandTotalPaise = 0;
        $today = date('Y-m-d');
        $tierRef = $party['tier_ref'] ?? null;

        foreach ($rawItems as $it) {
            $prodRef = $it['product_ref'];
            $qty = (int)$it['paid_qty'];

            if ($qty <= 0) {
                throw new ValidationException('INVALID_QTY', 'Paid quantity must be greater than zero.');
            }

            $product = $this->productRepo->findByRef($franchiseRef, $prodRef);
            if (!$product || $product['status'] !== 'ACTIVE') {
                throw new ValidationException('INVALID_PRODUCT', "Product {$prodRef} is inactive or not found.");
            }

            // Price resolution
            $pricing = $this->priceResolver->resolve($franchiseRef, $prodRef, $partyRef, $tierRef, $today);
            $rateFloat = (float)$pricing['rate'];
            $ratePaise = Money::fromDecimal((string)$rateFloat);
            $gstPct = (float)($product['gst_percent'] ?? 0.00);

            // Scheme calculation
            $schemeCalc = $this->schemeCalculator->calculate($franchiseRef, $tierRef, $prodRef, $qty, $today);
            $freeQty = (int)($schemeCalc['free_qty'] ?? 0);
            $schemeRef = !empty($schemeCalc['scheme_refs']) ? $schemeCalc['scheme_refs'][0] : null;

            // Line totals in paise
            $lineSubtotal = $ratePaise * $qty;
            $lineDiscount = 0;
            $taxable = $lineSubtotal - $lineDiscount;
            $lineGst = (int)round(($taxable * $gstPct) / 100);
            $lineGrand = $taxable + $lineGst;

            $subtotalPaise += $lineSubtotal;
            $discountTotalPaise += $lineDiscount;
            $gstTotalPaise += $lineGst;
            $grandTotalPaise += $lineGrand;

            $processedItems[] = [
                'item_ref'     => RefGenerator::generate('oit'),
                'product_ref'  => $prodRef,
                'paid_qty'     => $qty,
                'free_qty'     => $freeQty,
                'rate'         => Money::toDecimal($ratePaise),
                'rate_source'  => $pricing['rate_source'],
                'price_ref'    => $pricing['price_ref'] ?? null,
                'discount'     => Money::toDecimal($lineDiscount),
                'gst_percent'  => $gstPct,
                'line_total'   => Money::toDecimal($lineGrand),
                'scheme_ref'   => $schemeRef,
            ];
        }

        // Credit is a factual check at Submit/Confirm; Draft creation does not choose breach policy.
        $initialStatus = 'DRAFT';

        $orderData = [
            'order_ref'        => $orderRef,
            'order_no'         => $orderNo,
            'org_ref'          => $orgRef,
            'franchise_ref'    => $franchiseRef,
            'client_order_ref' => $clientOrderRef,
            'party_ref'        => $partyRef,
            'sales_user_ref'   => $salesUserRef,
            'channel'          => $channel,
            'order_date'       => $today,
            'billing_address'  => $party['billing_address'],
            'shipping_address' => $shippingAddress ?? $party['shipping_address'],
            'shipping_pincode' => $targetPincode,
            'pricing_tier_ref' => $tierRef,
            'status'           => $initialStatus,
            'territory_status' => $territoryStatus,
            'subtotal'         => Money::toDecimal($subtotalPaise),
            'discount_total'   => Money::toDecimal($discountTotalPaise),
            'gst_total'        => Money::toDecimal($gstTotalPaise),
            'grand_total'      => Money::toDecimal($grandTotalPaise),
            'remarks'          => $remarks,
            'created_by_ref'   => $actorRef,
        ];

        $this->orderRepo->create($orderData, $processedItems);

        return [
            'order_ref'     => $orderRef,
            'order_no'      => $orderNo,
            'status'        => $initialStatus,
            'grand_total'   => Money::toDecimal($grandTotalPaise),
            'items_count'   => count($processedItems),
        ];
    }

    public function confirmOrder(string $orgRef, string $franchiseRef, string $orderRef, string $actorRef): array
    {
        return $this->db->transaction(function () use ($orgRef, $franchiseRef, $orderRef, $actorRef): array {
            $order = $this->orderRepo->findByRefForUpdate($franchiseRef, $orderRef); if (!$order) throw new ValidationException('ORDER_NOT_FOUND', 'Order not found.');
            OrderStateMachine::assertCanTransition($order['status'], 'CONFIRMED');
            $facts = $this->validateAuthoritative($franchiseRef, $order, true);
            if ($facts['territory_status'] === TerritoryResolver::UNASSIGNED) throw new ValidationException('TERRITORY_POLICY_PENDING', 'Territory is UNASSIGNED; Order behavior requires approved policy.');
            if ($facts['territory_status'] === TerritoryResolver::CONFLICT) throw new ValidationException('TERRITORY_CONFLICT', 'Territory resolution returned CONFLICT.');
            $credit = $this->partyCreditService->checkForConfirmation($franchiseRef, $order['party_ref'], (string)$order['grand_total']);
            if ($credit['credit_breached']) throw new ValidationException('CREDIT_LIMIT_EXCEEDED', 'Projected exposure exceeds the party credit limit.');
            $allocLines = [];
            foreach ($facts['items'] as $it) $allocLines[] = ['order_item_ref' => $it['item_ref'], 'product_ref' => $it['product_ref'], 'qty' => (int)$it['paid_qty'] + (int)$it['free_qty'], 'min_shelf_days' => (int)($it['shelf_life_days'] ?? 0)];
            $reservations = $this->fefoAllocator->allocate($orgRef, $franchiseRef, $orderRef, $allocLines, $actorRef, false);
            if (!$this->orderRepo->updateStatusNoTransaction($franchiseRef, $orderRef, 'SUBMITTED', 'CONFIRMED', $actorRef, 'Order confirmed and stock reserved')) throw new ConflictException('ORDER_STATE_CHANGED', 'Order state changed concurrently.');
            return ['order_ref' => $orderRef, 'status' => 'CONFIRMED', 'reservations' => $reservations];
        });
    }

    public function submitOrder(string $orgRef, string $franchiseRef, string $orderRef, string $actorRef): array
    {
        $order = $this->orderRepo->findByRef($franchiseRef, $orderRef);
        if (!$order) throw new ValidationException('ORDER_NOT_FOUND', 'Order not found.');
        OrderStateMachine::assertCanTransition($order['status'], 'SUBMITTED');
        $facts = $this->validateAuthoritative($franchiseRef, $order, false);
        if ($facts['territory_status'] === TerritoryResolver::UNASSIGNED) throw new ValidationException('TERRITORY_POLICY_PENDING', 'Territory is UNASSIGNED; Order behavior requires approved policy.');
        if ($facts['territory_status'] === TerritoryResolver::CONFLICT) throw new ValidationException('TERRITORY_CONFLICT', 'Territory resolution returned CONFLICT.');
        if (!$this->orderRepo->updateStatusIfCurrent($franchiseRef, $orderRef, 'DRAFT', 'SUBMITTED', $actorRef, 'Order submitted')) throw new ConflictException('ORDER_STATE_CHANGED', 'Order state changed concurrently.');
        return ['order_ref' => $orderRef, 'status' => 'SUBMITTED'];
    }

    public function updateDraft(string $orgRef, string $franchiseRef, string $orderRef, string $partyRef, array $rawItems, ?string $shippingAddress, ?string $shippingPincode, ?string $remarks, string $actorRef): array
    {
        $order = $this->orderRepo->findByRef($franchiseRef, $orderRef); if (!$order) throw new ValidationException('ORDER_NOT_FOUND', 'Order not found.');
        if ($order['status'] !== 'DRAFT') throw new ValidationException('DRAFT_ONLY', 'Only DRAFT orders can be edited.');
        $party = $this->partyRepo->findByRef($franchiseRef, $partyRef); if (!$party || $party['status'] !== 'ACTIVE') throw new ValidationException('INVALID_PARTY', 'Party is inactive or does not exist.');
        $pricing = $this->priceItems($franchiseRef, $partyRef, $party['tier_ref'] ?? null, $rawItems);
        $targetPincode = $shippingPincode ?? $party['pincode']; $territoryStatus = 'OK'; if ($targetPincode) { $resolved = $this->territoryResolver->resolve($franchiseRef, $partyRef, $targetPincode, null, date('Y-m-d')); $territoryStatus = $resolved['status'] === TerritoryResolver::MATCHED ? 'OK' : $resolved['status']; }
        $data = ['org_ref' => $orgRef, 'party_ref' => $partyRef, 'sales_user_ref' => $order['sales_user_ref'], 'billing_address' => $party['billing_address'], 'shipping_address' => $shippingAddress ?? $party['shipping_address'], 'shipping_pincode' => $targetPincode, 'pricing_tier_ref' => $party['tier_ref'] ?? null, 'territory_status' => $territoryStatus, 'subtotal' => Money::toDecimal($pricing['subtotal']), 'discount_total' => Money::toDecimal($pricing['discount']), 'gst_total' => Money::toDecimal($pricing['gst']), 'grand_total' => Money::toDecimal($pricing['grand']), 'remarks' => $remarks];
        if (!$this->orderRepo->updateDraft($franchiseRef, $orderRef, $data, $pricing['items'], $actorRef)) throw new ConflictException('ORDER_STATE_CHANGED', 'Draft changed concurrently.');
        return ['order_ref' => $orderRef, 'status' => 'DRAFT', 'grand_total' => $data['grand_total'], 'items_count' => count($pricing['items'])];
    }

    public function deleteDraft(string $franchiseRef, string $orderRef): void
    {
        $order = $this->orderRepo->findByRef($franchiseRef, $orderRef); if (!$order) throw new ValidationException('ORDER_NOT_FOUND', 'Order not found.'); if ($order['status'] !== 'DRAFT') throw new ValidationException('DRAFT_ONLY', 'Only DRAFT orders can be deleted.');
        if (!$this->orderRepo->deleteDraft($franchiseRef, $orderRef)) throw new ConflictException('ORDER_STATE_CHANGED', 'Draft changed concurrently.');
    }

    private function validateAuthoritative(string $franchiseRef, array $order, bool $checkStock): array
    {
        $party = $this->partyRepo->findByRef($franchiseRef, $order['party_ref']); if (!$party || $party['status'] !== 'ACTIVE') throw new ValidationException('INVALID_PARTY', 'Party is inactive or does not exist.');
        $pincode = $order['shipping_pincode'] ?? $party['pincode']; $territory = $pincode ? $this->territoryResolver->resolve($franchiseRef, $order['party_ref'], $pincode, null, date('Y-m-d')) : ['status' => TerritoryResolver::UNASSIGNED];
        $items = $this->orderRepo->getItems($franchiseRef, $order['order_ref']); if (!$items) throw new ValidationException('EMPTY_ORDER', 'Order has no items.');
        foreach ($items as $item) {
            $product = $this->productRepo->findByRef($franchiseRef, $item['product_ref']);
            if (!$product || $product['status'] !== 'ACTIVE') throw new ValidationException('INVALID_PRODUCT', 'Order contains an inactive or missing product.');
            $resolved = $this->priceResolver->resolve($franchiseRef, $item['product_ref'], $order['party_ref'], $party['tier_ref'] ?? null, date('Y-m-d'));
            if (Money::fromDecimal((string)$resolved['rate']) !== Money::fromDecimal((string)$item['rate']) || ($resolved['price_ref'] ?? null) !== ($item['price_ref'] ?? null)) {
                throw new ValidationException('STALE_ORDER_PRICING', 'Order pricing changed; refresh the draft before continuing.');
            }
            $scheme = $this->schemeCalculator->calculate($franchiseRef, $party['tier_ref'] ?? null, $item['product_ref'], (int)$item['paid_qty'], date('Y-m-d'));
            if ((int)($scheme['free_qty'] ?? 0) !== (int)($item['free_qty'] ?? 0) || (($scheme['scheme_refs'][0] ?? null) !== ($item['scheme_ref'] ?? null))) {
                throw new ValidationException('STALE_ORDER_SCHEME', 'Order scheme eligibility changed; refresh the draft before continuing.');
            }
        }
        return ['party' => $party, 'territory_status' => $territory['status'], 'items' => $items];
    }

    private function priceItems(string $franchiseRef, string $partyRef, ?string $tierRef, array $rawItems): array
    {
        if (!$rawItems) throw new ValidationException('EMPTY_ORDER', 'At least one order item is required.'); $items = []; $subtotal = 0; $discount = 0; $gst = 0; $grand = 0; $today = date('Y-m-d');
        foreach ($rawItems as $it) { $prodRef = (string)($it['product_ref'] ?? ''); $qty = (int)($it['paid_qty'] ?? 0); if ($qty <= 0) throw new ValidationException('INVALID_QTY', 'Paid quantity must be greater than zero.'); $product = $this->productRepo->findByRef($franchiseRef, $prodRef); if (!$product || $product['status'] !== 'ACTIVE') throw new ValidationException('INVALID_PRODUCT', "Product {$prodRef} is inactive or not found."); $resolved = $this->priceResolver->resolve($franchiseRef, $prodRef, $partyRef, $tierRef, $today); $rate = Money::fromDecimal((string)$resolved['rate']); $lineSubtotal = $rate * $qty; $lineGst = (int)round(($lineSubtotal * (float)($product['gst_percent'] ?? 0)) / 100); $scheme = $this->schemeCalculator->calculate($franchiseRef, $tierRef, $prodRef, $qty, $today); $items[] = ['item_ref' => RefGenerator::generate('oit'), 'product_ref' => $prodRef, 'paid_qty' => $qty, 'free_qty' => (int)($scheme['free_qty'] ?? 0), 'rate' => Money::toDecimal($rate), 'rate_source' => $resolved['rate_source'], 'price_ref' => $resolved['price_ref'] ?? null, 'discount' => '0.00', 'gst_percent' => (float)($product['gst_percent'] ?? 0), 'line_total' => Money::toDecimal($lineSubtotal + $lineGst), 'scheme_ref' => $scheme['scheme_refs'][0] ?? null]; $subtotal += $lineSubtotal; $gst += $lineGst; $grand += $lineSubtotal + $lineGst; }
        return ['items' => $items, 'subtotal' => $subtotal, 'discount' => $discount, 'gst' => $gst, 'grand' => $grand];
    }

    public function cancelOrder(string $orgRef, string $franchiseRef, string $orderRef, string $actorRef, string $reason): bool
    {
        $order = $this->orderRepo->findByRef($franchiseRef, $orderRef);
        if (!$order) {
            throw new ValidationException('ORDER_NOT_FOUND', 'Order not found.');
        }

        OrderStateMachine::assertCanTransition($order['status'], 'CANCELLED');

        return $this->db->transaction(function () use ($orgRef, $franchiseRef, $orderRef, $actorRef, $reason): bool {
            $locked = $this->orderRepo->findByRefForUpdate($franchiseRef, $orderRef);
            if (!$locked) throw new ValidationException('ORDER_NOT_FOUND', 'Order not found.');
            OrderStateMachine::assertCanTransition($locked['status'], 'CANCELLED');
            $this->fefoAllocator->releaseOrderStock($orgRef, $franchiseRef, $orderRef, $actorRef, false);
            if (!$this->orderRepo->updateStatusNoTransaction($franchiseRef, $orderRef, $locked['status'], 'CANCELLED', $actorRef, $reason)) {
                throw new ConflictException('ORDER_STATE_CHANGED', 'Order state changed concurrently.');
            }
            return true;
        });
    }
}
