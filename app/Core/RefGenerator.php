<?php
declare(strict_types=1);
namespace App\Core;

final class RefGenerator
{
    // Crockford base32 alphabet (excludes I, L, O, U to avoid visual ambiguity)
    private const ALPHABET = '0123456789ABCDEFGHJKMNPQRSTVWXYZ';

    private const PREFIXES = [
        'ORG','FRN','USR','PRD','CAT','TIR','PRC','SCH','RUL',
        'PAR','TER','OVR','LED','FUP','ACT','ORD','ITM','BAT',
        'MOV','RSV','IVC','IVI','DSP','TRN','PAY','ALC','SRC',
        'EVT','NTF','JOB','AUD','SES','FAM','KEY','INV','STG',
    ];

    /**
     * Generate a ref: PREFIX-XXXXXXXXXXXXXXXX (16 base32 chars = 80 bits entropy)
     * Example: ORD-7K3M9Q2XW4T8HBEA
     */
    public static function generate(string $prefix): string
    {
        return self::make($prefix);
    }

    public static function make(string $prefix): string
    {
        $bytes = random_bytes(10); // 80 bits

        // Convert bytes to binary string
        $bits = '';
        foreach (str_split($bytes) as $byte) {
            $bits .= str_pad(decbin(ord($byte)), 8, '0', STR_PAD_LEFT);
        }

        // Group into 5-bit chunks, map to alphabet
        $out = '';
        foreach (str_split($bits, 5) as $chunk) {
            $out .= self::ALPHABET[bindec($chunk)];
        }

        return $prefix . '-' . $out; // e.g. ORD-7K3M9Q2XW4T8HBEA
    }

    /**
     * Validate a ref format.
     */
    public static function isValid(string $ref, string $prefix): bool
    {
        return (bool) preg_match('/^' . preg_quote($prefix, '/') . '-[0-9A-HJKMNP-TV-Z]{16}$/', $ref);
    }
}
