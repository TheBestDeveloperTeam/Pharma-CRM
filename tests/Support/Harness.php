<?php
declare(strict_types=1);
namespace Tests\Support;

final class Harness
{
    private static bool $booted = false;

    public static function boot(): void
    {
        if (self::$booted) return;
        self::$booted = true;

        require_once __DIR__ . '/../../bootstrap/app.php';
    }
}
