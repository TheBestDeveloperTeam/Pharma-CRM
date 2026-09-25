<?php
declare(strict_types=1);
namespace App\Domain\Pricing;

use App\Repositories\Contracts\{ProductPriceRepositoryInterface, ProductRepositoryInterface};
use App\Core\Exceptions\BusinessRuleException;

final class PriceResolver
{
    public function __construct(
        private ProductPriceRepositoryInterface $priceRepo,
        private ProductRepositoryInterface $productRepo,
    ) {}

    /**
     * Resolve effective price according to Section 10.3 Priority Chain:
     * 1. Party-specific rule (rate_source = 'PARTY')
     * 2. Tier-based rule (rate_source = 'TIER')
     * 3. Product franchise_rate default (rate_source = 'DEFAULT')
     * 4. Throws PRICE_NOT_FOUND if no valid rate exists
     *
     * @return array{rate: float, rate_source: string, price_ref: ?string}
     */
    public function resolve(string $franchiseRef, string $productRef, ?string $partyRef, ?string $tierRef, string $date): array
    {
        $candidates = $this->priceRepo->findApplicable($franchiseRef, $productRef, $partyRef, $tierRef, $date);

        // 1. Party-specific rule
        if (!empty($partyRef)) {
            foreach ($candidates as $rule) {
                if ($rule['party_ref'] === $partyRef) {
                    return [
                        'rate'        => (float)$rule['rate'],
                        'mrp'         => (float)($rule['mrp'] ?? 0),
                        'pts'         => (float)($rule['pts'] ?? 0),
                        'net_rate'    => (float)($rule['net_rate'] ?? $rule['rate']),
                        'rate_source' => 'PARTY',
                        'price_ref'   => $rule['price_ref'],
                    ];
                }
            }
        }

        // 2. Tier-based rule
        if (!empty($tierRef)) {
            foreach ($candidates as $rule) {
                if ($rule['tier_ref'] === $tierRef) {
                    return [
                        'rate'        => (float)$rule['rate'],
                        'mrp'         => (float)($rule['mrp'] ?? 0),
                        'pts'         => (float)($rule['pts'] ?? 0),
                        'net_rate'    => (float)($rule['net_rate'] ?? $rule['rate']),
                        'rate_source' => 'TIER',
                        'price_ref'   => $rule['price_ref'],
                    ];
                }
            }
        }

        // 3. Product franchise_rate default
        $prod = $this->productRepo->findByRef($franchiseRef, $productRef);
        if ($prod && (float)$prod['franchise_rate'] > 0) {
            return [
                'rate'        => (float)$prod['franchise_rate'],
                'mrp'         => (float)($prod['mrp'] ?? 0),
                'pts'         => (float)($prod['pts'] ?? 0),
                'net_rate'    => (float)$prod['franchise_rate'],
                'rate_source' => 'DEFAULT',
                'price_ref'   => null,
            ];
        }

        // 4. None found
        throw new BusinessRuleException('PRICE_NOT_FOUND', "No valid price found for product {$productRef}.");
    }
}
