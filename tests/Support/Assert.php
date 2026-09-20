<?php
declare(strict_types=1);
namespace Tests\Support;

final class Assert
{
    public static function equals(mixed $expected, mixed $actual, string $msg = ''): void
    {
        self::eq($expected, $actual, $msg);
    }

    public static function eq(mixed $expected, mixed $actual, string $msg = ''): void
    {
        if ($expected !== $actual) {
            $e = json_encode($expected);
            $a = json_encode($actual);
            throw new \AssertionError(($msg ? "$msg: " : '') . "Expected $e, got $a");
        }
    }

    public static function status(\App\Core\Response $r, int $expected, string $msg = ''): void
    {
        self::eq($expected, $r->status(), $msg ?: "HTTP status");
    }

    public static function jsonPath(\App\Core\Response $r, string $path, mixed $expected): void
    {
        $data = json_decode($r->body(), true);
        $keys = explode('.', $path);
        foreach ($keys as $k) {
            if (!is_array($data) || !array_key_exists($k, $data)) {
                throw new \AssertionError("Path [$path] not found in response");
            }
            $data = $data[$k];
        }
        self::eq($expected, $data, "jsonPath[$path]");
    }

    public static function throws(callable $fn, string $exceptionClass, string $msg = ''): void
    {
        try {
            $fn();
            throw new \AssertionError(($msg ?: "Expected $exceptionClass") . " but none thrown");
        } catch (\Throwable $e) {
            if (!($e instanceof $exceptionClass)) {
                throw new \AssertionError("Expected $exceptionClass, got " . get_class($e) . ": " . $e->getMessage());
            }
        }
    }

    public static function true(mixed $value, string $msg = ''): void
    {
        if (!$value) {
            throw new \AssertionError($msg ?: "Expected true, got " . json_encode($value));
        }
    }

    public static function false(mixed $value, string $msg = ''): void
    {
        if ($value) {
            throw new \AssertionError($msg ?: "Expected false, got " . json_encode($value));
        }
    }
}

