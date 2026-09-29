<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap/app.php';

$router = new \App\Core\Router();
$routesFn = require __DIR__ . '/../bootstrap/routes.php';
$routesFn($router);

// Inspect router routes via reflection
$reflector = new \ReflectionClass($router);
$prop = $reflector->getProperty('routes');
$prop->setAccessible(true);
$routes = $prop->getValue($router);

$yaml = file_get_contents(__DIR__ . '/../docs/front-end/openapi.yaml');

$missingMethod = [];
$apiRoutesCount = 0;

foreach ($routes as $route) {
    $m = strtolower($route['method']);
    $p = $route['path'];

    // Skip web HTML routes (start with /admin/, /super/, /sales/, /portal/ but not /api/v1/)
    if (!str_starts_with($p, '/api/v1') && !str_starts_with($p, '/geo') && !str_starts_with($p, '/health') && !str_starts_with($p, '/ready')) {
        continue;
    }

    $apiRoutesCount++;

    $cleanP = preg_replace('#^/api/v1#', '', $p);
    if ($cleanP === '') {
        $cleanP = '/';
    }

    // Find the path block in openapi.yaml
    $pos = strpos($yaml, "\n  " . $p . ":");
    if ($pos === false) {
        $pos = strpos($yaml, "\n  " . $cleanP . ":");
    }

    if ($pos === false) {
        $missingMethod[] = "Path not found: {$route['method']} {$p}";
        continue;
    }

    // Check if the method (e.g. "    get:", "    post:") exists in this path block
    // Find next path or end of file
    $nextPathPos = strpos($yaml, "\n  /", $pos + 5);
    $blockLen = ($nextPathPos !== false) ? ($nextPathPos - $pos) : 2000;
    $pathBlock = substr($yaml, $pos, $blockLen);

    if (!preg_match("/\n    " . $m . ":/i", $pathBlock)) {
        $missingMethod[] = "Method {$route['method']} missing under path {$cleanP}";
    }
}

echo "Total REST API routes checked: {$apiRoutesCount}\n";
echo "Missing methods/paths in openapi.yaml: " . count($missingMethod) . "\n";
foreach ($missingMethod as $err) {
    echo "  - {$err}\n";
}
