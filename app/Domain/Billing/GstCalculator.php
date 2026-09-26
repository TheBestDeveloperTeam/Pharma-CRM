<?php
declare(strict_types=1);

namespace App\Domain\Billing;

use App\Core\Exceptions\ValidationException;
use App\Support\Money;

/** Pure tax calculator; place-of-supply selection intentionally stays outside it. */
final class GstCalculator
{
    public const INTRASTATE = 'INTRASTATE';
    public const INTERSTATE = 'INTERSTATE';

    /** @param array<int,array{taxable_amount:string|float|int,gst_percent:string|float|int}> $lines */
    public function calculate(array $lines, ?string $jurisdiction): array
    {
        if (!in_array($jurisdiction, [self::INTRASTATE, self::INTERSTATE], true)) {
            throw new ValidationException('GST_JURISDICTION_UNSUPPORTED', 'A supported domestic GST jurisdiction is required before invoicing.');
        }
        $result = ['taxable_total' => 0, 'cgst_total' => 0, 'sgst_total' => 0, 'igst_total' => 0, 'tax_total' => 0, 'grand_total' => 0, 'lines' => []];
        foreach ($lines as $line) {
            $taxable = Money::fromDecimal($line['taxable_amount']);
            // GST rate is stored as percent with two decimal places.  Convert it
            // to basis points so tax authority never depends on binary floats.
            $rateBasisPoints = Money::fromDecimal($line['gst_percent']);
            if ($taxable < 0 || $rateBasisPoints < 0 || $rateBasisPoints > 10000) throw new ValidationException('INVALID_GST_INPUT', 'Taxable value and GST rate must be valid non-negative values.');
            $tax = intdiv(($taxable * $rateBasisPoints) + 5000, 10000);
            $cgst = $jurisdiction === self::INTRASTATE ? intdiv($tax, 2) : 0; $sgst = $jurisdiction === self::INTRASTATE ? $tax - $cgst : 0; $igst = $jurisdiction === self::INTERSTATE ? $tax : 0;
            $result['taxable_total'] += $taxable; $result['cgst_total'] += $cgst; $result['sgst_total'] += $sgst; $result['igst_total'] += $igst; $result['tax_total'] += $tax; $result['grand_total'] += $taxable + $tax;
            $result['lines'][] = ['taxable_amount' => $taxable, 'cgst_amount' => $cgst, 'sgst_amount' => $sgst, 'igst_amount' => $igst, 'total_tax' => $tax, 'line_total' => $taxable + $tax];
        }
        foreach (['taxable_total','cgst_total','sgst_total','igst_total','tax_total','grand_total'] as $key) $result[$key] = Money::toDecimal($result[$key]);
        foreach ($result['lines'] as &$line) foreach ($line as $key => $value) $line[$key] = Money::toDecimal($value); unset($line);
        return $result;
    }
}
