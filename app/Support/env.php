<?php
declare(strict_types=1);

namespace App\Support {
    final class Env
    {
        private static array $loaded = [];

        /**
         * Load .env file into environment.
         * Handles: KEY=VALUE, KEY="quoted value", KEY='single quoted', # comments
         */
        public static function load(string $path): void
        {
            if (!file_exists($path)) {
                throw new \RuntimeException(".env file not found at: $path");
            }

            $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

            foreach ($lines as $line) {
                $line = trim($line);

                // Skip comments and empty lines
                if ($line === '' || $line[0] === '#') {
                    continue;
                }

                // Must contain = sign
                if (!str_contains($line, '=')) {
                    continue;
                }

                [$name, $value] = explode('=', $line, 2);
                $name  = trim($name);
                $value = trim($value);

                // Strip inline comments (value must not be quoted for this)
                if (!in_array($value[0] ?? '', ['"', "'"], true)) {
                    if (($pos = strpos($value, ' #')) !== false) {
                        $value = trim(substr($value, 0, $pos));
                    }
                }

                // Strip surrounding quotes
                if (strlen($value) >= 2) {
                    $first = $value[0];
                    $last  = $value[-1];
                    if (($first === '"' && $last === '"') || ($first === "'" && $last === "'")) {
                        $value = substr($value, 1, -1);
                    }
                }

                // Set in environment
                if (!array_key_exists($name, $_ENV)) {
                    $_ENV[$name]    = $value;
                    $_SERVER[$name] = $value;
                    putenv("$name=$value");
                }

                self::$loaded[$name] = $value;
            }
        }

        /**
         * Get an env value. Reads from $_ENV → getenv() → default.
         */
        public static function get(string $key, mixed $default = null): mixed
        {
            return $_ENV[$key] ?? getenv($key) ?: $default;
        }
    }

    function env(string $key, mixed $default = null): mixed
    {
        return Env::get($key, $default);
    }
}

namespace {
    if (!function_exists('env')) {
        function env(string $key, mixed $default = null): mixed
        {
            return \App\Support\Env::get($key, $default);
        }
    }
}
