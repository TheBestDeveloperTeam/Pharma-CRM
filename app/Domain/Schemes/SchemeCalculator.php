<?php
declare(strict_types=1);
namespace App\Domain\Schemes;

use App\Repositories\Contracts\SchemeRepositoryInterface;

final class SchemeCalculator
{
    public function __construct(private SchemeRepositoryInterface $schemeRepo) {}

    /**
     * Calculate free goods scheme according to Section 10.4 Algorithm:
     *
     * @return array{paid_qty: int, free_qty: int, total_fulfil: int, scheme_refs: array<string>}
     */
    public function calculate(string $franchiseRef, ?string $tierRef, string $productRef, int $orderedQty, string $date): array
    {
        if ($orderedQty <= 0) {
            return [
                'paid_qty'     => 0,
                'free_qty'     => 0,
                'total_fulfil' => 0,
                'scheme_refs'  => [],
            ];
        }

        $rules = $this->schemeRepo->findApplicableRules($franchiseRef, $productRef, $tierRef, $date);

        if (empty($rules)) {
            return [
                'paid_qty'     => $orderedQty,
                'free_qty'     => 0,
                'total_fulfil' => $orderedQty,
                'scheme_refs'  => [],
            ];
        }

        // Group rules by scheme_ref
        $schemesMap = [];
        foreach ($rules as $r) {
            $sRef = $r['scheme_ref'];
            $schemesMap[$sRef][] = $r;
        }

        // Check if any matching scheme allows stacking
        $stackingAllowed = false;
        foreach ($rules as $r) {
            if (!empty($r['stacking_allowed'])) {
                $stackingAllowed = true;
                break;
            }
        }

        $totalFree = 0;
        $appliedSchemes = [];

        if (!$stackingAllowed) {
            // Non-stacking: pick single rule that yields highest free goods
            $bestFree = 0;
            $bestSchemeRef = null;

            foreach ($rules as $rule) {
                $minQty = (int)$rule['min_qty'];
                $maxQty = $rule['max_qty'] !== null ? (int)$rule['max_qty'] : null;

                if ($orderedQty < $minQty) continue;
                if ($maxQty !== null && $orderedQty > $maxQty) continue;

                $sets = (int) floor($orderedQty / $minQty);
                $free = $sets * (int)$rule['free_qty'];

                if ($free > $bestFree) {
                    $bestFree = $free;
                    $bestSchemeRef = $rule['scheme_ref'];
                }
            }

            if ($bestFree > 0 && $bestSchemeRef !== null) {
                $totalFree = $bestFree;
                $appliedSchemes[] = $bestSchemeRef;
            }
        } else {
            // Stacking allowed: accumulate across applicable scheme rules
            foreach ($rules as $rule) {
                $minQty = (int)$rule['min_qty'];
                $maxQty = $rule['max_qty'] !== null ? (int)$rule['max_qty'] : null;

                if ($orderedQty < $minQty) continue;
                if ($maxQty !== null && $orderedQty > $maxQty) continue;

                $sets = (int) floor($orderedQty / $minQty);
                $free = $sets * (int)$rule['free_qty'];

                if ($free > 0) {
                    $totalFree += $free;
                    if (!in_array($rule['scheme_ref'], $appliedSchemes, true)) {
                        $appliedSchemes[] = $rule['scheme_ref'];
                    }
                }
            }
            // Cap total free at 100% of ordered_qty
            $totalFree = min($totalFree, $orderedQty);
        }

        return [
            'paid_qty'     => $orderedQty,
            'free_qty'     => $totalFree,
            'total_fulfil' => $orderedQty + $totalFree,
            'scheme_refs'  => $appliedSchemes,
        ];
    }
}
