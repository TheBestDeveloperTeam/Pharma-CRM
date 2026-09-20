<?php
declare(strict_types=1);
namespace App\Core;

final class RequestId
{
    private static string $current = '';

    public static function set(string $id): void
    {
        self::$current = $id;
    }

    public static function current(): string
    {
        return self::$current;
    }

    public static function generate(): string
    {
        return 'req_' . bin2hex(random_bytes(12)); // 24 hex chars
    }
}
