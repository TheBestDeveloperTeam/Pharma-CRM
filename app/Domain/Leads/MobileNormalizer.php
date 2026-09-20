<?php
declare(strict_types=1);
namespace App\Domain\Leads;

final class MobileNormalizer
{
    /**
     * Normalize Indian mobile numbers:
     * - Strips non-digits
     * - Strips leading country code 91 (if total length > 10) or leading 0
     * - Returns 10 digits or cleaned string
     */
    public static function normalize(string $mobile): string
    {
        $digits = (string)preg_replace('/\D/', '', $mobile);

        if (str_starts_with($digits, '91') && strlen($digits) > 10) {
            $digits = substr($digits, 2);
        } elseif (str_starts_with($digits, '0') && strlen($digits) > 10) {
            $digits = substr($digits, 1);
        }

        return $digits;
    }
}
