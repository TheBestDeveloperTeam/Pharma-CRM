<?php
declare(strict_types=1);
namespace App\Support;

final class Config
{
    private static array $cache = [];

    /**
     * Get config value. Dot notation: 'auth.access_ttl'
     * Loads from app/Config/<file>.php on first access.
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        [$file, $rest] = array_pad(explode('.', $key, 2), 2, null);

        if (!isset(self::$cache[$file])) {
            $path = __DIR__ . '/../../app/Config/' . $file . '.php';
            self::$cache[$file] = file_exists($path) ? require $path : [];
        }

        if ($rest === null) {
            return self::$cache[$file] ?? $default;
        }

        // Dot-navigate into nested array
        $data = self::$cache[$file];
        foreach (explode('.', $rest) as $segment) {
            if (!is_array($data) || !array_key_exists($segment, $data)) {
                return $default;
            }
            $data = $data[$segment];
        }
        return $data;
    }
}
