<?php
declare(strict_types=1);
namespace App\Domain\Orders;

use App\Core\RefGenerator;
use App\Core\SequenceService;
use App\Core\Exceptions\ValidationException;
use App\Core\Exceptions\ConflictException;
use App\Support\Money;
use App\Domain\Pricing\PriceResolver;
use App\Domain\Schemes\SchemeCalculator;
use App\Domain\Territory\TerritoryValidator;
use App\Domain\Inventory\FefoAllocator;
use App\Repositories\Contracts\OrderRepositoryInterface;
use App\Repositories\Contracts\PartyRepositoryInterface;
use App\Repositories\Contracts\ProductRepositoryInterface;

final class OrderService
{
    public function __construct(
        private OrderRepositoryInterface $orderRepo,
        private PartyRepositoryInterface $partyRepo,
        private ProductRepositoryInterface $productRepo,
        private PriceResolver $priceResolver,
        private SchemeCalculator $schemeCalculator,
        private TerritoryValidator $territoryValidator,
        private CreditRuleService $creditRuleService,
        private FefoAllocator $fefoAllocator,
        private SequenceService $sequenceService,
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

        // 3. Validate Territory
        $targetPincode = $shippingPincode ?? $party['pincode'];
        $today = date('Y-m-d');

        $territoryStatus = 'OK';
        if ($targetPincode) {
            $terrCheck = $this->territoryValidator->validate($franchiseRef, $partyRef, $targetPincode, $today);
            if ($terrCheck['status'] === TerritoryValidator::BLOCKED) {
                $territoryStatus = 'BLOCKED';
                throw new ValidationException('TERRITORY_RESTRICTION', $terrCheck['message'] ?? 'Territory restriction.');
            }
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

        // 5. Credit Check
        $creditCheck = $this->creditRuleService->checkCredit($franchiseRef, $partyRef, $grandTotalPaise);
        $initialStatus = 'SUBMITTED';
        if (!$creditCheck['allowed'] && $creditCheck['action'] === 'HOLD') {
            $initialStatus = 'ON_HOLD';
            $remarks = ($remarks ? $remarks . ' | ' : '') . $creditCheck['reason'];
        }

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
            'shipping_address' => $shippingAddress ?? $party['shipping_address'],
            'shipping_pincode' => $targetPincode,
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
        $order = $this->orderRepo->findByRef($franchiseRef, $orderRef);
        if (!$order) {
            throw new ValidationException('ORDER_NOT_FOUND', 'Order not found.');
        }

        OrderStateMachine::assertCanTransition($order['status'], 'CONFIRMED');

        $items = $this->orderRepo->getItems($franchiseRef, $orderRef);
        if (empty($items)) {
            throw new ValidationException('EMPTY_ORDER', 'Order has no items.');
        }

        // Build allocation request list (total qty = paid_qty + free_qty)
        $allocLines = [];
        foreach ($items as $it) {
            $totalQty = (int)$it['paid_qty'] + (int)$it['free_qty'];
            $allocLines[] = [
                'order_item_ref'  => $it['item_ref'],
                'product_ref'     => $it['product_ref'],
                'qty'             => $totalQty,
                'min_shelf_days'  => (int)($it['shelf_life_days'] ?? 0),
            ];
        }

        // FEFO Stock allocation
        $reservations = $this->fefoAllocator->allocate($orgRef, $franchiseRef, $orderRef, $allocLines, $actorRef);

        // Update status to CONFIRMED
        $this->orderRepo->updateStatus($franchiseRef, $orderRef, 'CONFIRMED', $actorRef, 'Order confirmed and stock reserved');

        return [
            'order_ref'    => $orderRef,
            'status'       => 'CONFIRMED',
            'reservations' => $reservations,
        ];
    }

    public function cancelOrder(string $orgRef, string $franchiseRef, string $orderRef, string $actorRef, string $reason): bool
    {
        $order = $this->orderRepo->findByRef($franchiseRef, $orderRef);
        if (!$order) {
            throw new ValidationException('ORDER_NOT_FOUND', 'Order not found.');
        }

        OrderStateMachine::assertCanTransition($order['status'], 'CANCELLED');

        // Release any reserved stock
        $this->fefoAllocator->releaseOrderStock($orgRef, $franchiseRef, $orderRef, $actorRef);

        // Transition order
        return $this->orderRepo->updateStatus($franchiseRef, $orderRef, 'CANCELLED', $actorRef, $reason);
    }
}
