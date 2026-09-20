<?php
declare(strict_types=1);
namespace App\Support;

final class Money
{
    /** Multiply rate × quantity in paise (avoids floating-point errors) */
    public static function multiply(string|float $rate, int $qty): string
    {
        $ratePaise = (int) round((float)$rate * 100);
        $totalPaise = $ratePaise * $qty;
        return number_format($totalPaise / 100, 2, '.', '');
    }

    /** Apply percentage discount in paise */
    public static function discount(string|float $amount, float $discountPercent): string
    {
        $amountPaise   = (int) round((float)$amount * 100);
        $discountPaise = (int) round($amountPaise * $discountPercent / 100);
        return number_format(($amountPaise - $discountPaise) / 100, 2, '.', '');
    }

    /** Calculate GST (percentage of subtotal) */
    public static function applyGst(string|float $subtotal, float $gstPercent): array
    {
        $subtotalPaise = (int) round((float)$subtotal * 100);
        $gstPaise      = (int) round($subtotalPaise * $gstPercent / 100);
        $totalPaise    = $subtotalPaise + $gstPaise;
        return [
            'subtotal'    => number_format($subtotalPaise / 100, 2, '.', ''),
            'gst_amount'  => number_format($gstPaise / 100, 2, '.', ''),
            'grand_total' => number_format($totalPaise / 100, 2, '.', ''),
        ];
    }

    /** Sum array of string amounts */
    public static function sum(array $amounts): string
    {
        $totalPaise = 0;
        foreach ($amounts as $amount) {
            $totalPaise += (int) round((float)$amount * 100);
        }
        return number_format($totalPaise / 100, 2, '.', '');
    }

    /** Compare two money amounts. Returns -1, 0, 1 */
    public static function compare(string|float $a, string|float $b): int
    {
        $aPaise = (int) round((float)$a * 100);
        $bPaise = (int) round((float)$b * 100);
        return $aPaise <=> $bPaise;
    }

    /** Convert decimal string or float to integer paise */
    public static function fromDecimal(string|float|int $amount): int
    {
        return (int) round((float)$amount * 100);
    }

    /** Convert integer paise to decimal string */
    public static function toDecimal(int $paise): string
    {
        return number_format($paise / 100, 2, '.', '');
    }

    /** Format paise as readable string */
    public static function format(int $paise): string
    {
        return number_format($paise / 100, 2, '.', ',');
    }
}
