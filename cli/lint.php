<?php
declare(strict_types=1);

// Recursively find files matching glob pattern
function rglob(string $pattern, int $flags = 0): array {
    $files = glob($pattern, $flags) ?: [];
    foreach (glob(dirname($pattern) . '/*', GLOB_ONLYDIR | GLOB_NOSORT) as $dir) {
        $files = array_merge($files, rglob($dir . '/' . basename($pattern), $flags));
    }
    return $files;
}

$rules = [
    ['No innerHTML in JS', 'public/assets/js/*.js',
        '/\.innerHTML\s*=/', true],
    ['No CDN URLs in JS/CSS', 'public/assets/js/*.js',
        '/(cdn\.|unpkg\.com|jsdelivr|cdnjs|googleapis|bootstrapcdn)/i', true],
    ['No inline <script> in views', 'app/Views/*.php',
        '/<script(?!\s+type=["\']module["\'])/i', true],
    ['No localStorage token storage', 'public/assets/js/*.js',
        '/localStorage\.(setItem|getItem)\([\'"][^"\']*(?:token|refresh|bearer|auth)/i', true],
];

$failures = 0;
foreach ($rules as [$desc, $glob, $pattern, $shouldNotExist]) {
    $files = rglob(__DIR__ . '/../' . $glob);
    foreach ($files as $file) {
        $content = (string)file_get_contents($file);
        $matches = preg_match($pattern, $content);
        if ($shouldNotExist && $matches) {
            echo "LINT FAIL [$desc]: $file\n";
            $failures++;
        }
    }
}

echo $failures === 0 ? "All lint checks passed.\n" : "$failures lint failures found.\n";
exit($failures > 0 ? 1 : 0);
