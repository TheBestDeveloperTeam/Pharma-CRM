<?php
declare(strict_types=1);

/**
 * PSR-4 autoloader — maps App\ to app/ directory.
 * No Composer required. Works on cPanel without shell access.
 */
spl_autoload_register(function (string $class): void {
    $prefixes = [
        'App\\'   => __DIR__ . '/../app/',
        'Tests\\' => __DIR__ . '/../tests/',
    ];

    foreach ($prefixes as $prefix => $baseDir) {
        if (strncmp($prefix, $class, strlen($prefix)) === 0) {
            $relativeClass = substr($class, strlen($prefix));
            $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';

            if (file_exists($file)) {
                require $file;
            }
            return;
        }
    }
});
