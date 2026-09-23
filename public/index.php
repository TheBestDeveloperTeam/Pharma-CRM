<?php
declare(strict_types=1);

/**
 * Front Controller & Routing Engine
 */

// Basic error reporting
error_reporting(E_ALL);
ini_set('display_errors', '1');

// Autoloader for the app directory
spl_autoload_register(function ($class) {
    $prefix = 'App\\';
    $base_dir = __DIR__ . '/../app/';
    
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }
    
    $relative_class = substr($class, $len);
    $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';
    
    if (file_exists($file)) {
        require $file;
    }
});

// Load config/environment (placeholder for dotenv logic)
$envFile = __DIR__ . '/../.env';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue;
        list($name, $value) = explode('=', $line, 2);
        $_ENV[trim($name)] = trim($value);
    }
}

// Request processing
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$method = $_SERVER['REQUEST_METHOD'];

// Extremely lightweight routing
$routes = [
    'GET' => [
        '/' => ['App\Controllers\Web\DashboardController', 'index'],
        '/login' => ['App\Controllers\Web\AuthController', 'login'],
        '/doctors' => ['App\Controllers\Web\DoctorController', 'index'],
        '/orders' => ['App\Controllers\Web\OrderController', 'index'],
        '/api/v1/dashboard/stats' => ['App\Controllers\Api\DashboardController', 'stats'],
        '/api/v1/users' => ['App\Controllers\Api\UserController', 'index'],
        '/api/v1/doctors' => ['App\Controllers\Api\DoctorController', 'index'],
        '/api/v1/orders' => ['App\Controllers\Api\OrderController', 'index'],
        '/api/v1/products' => ['App\Controllers\Api\ProductController', 'index'],
        '/api/v1/leads' => ['App\Controllers\Api\LeadController', 'index']
    ],
    'POST' => [
        '/api/v1/auth/token' => ['App\Controllers\Api\AuthController', 'token'],
        '/api/v1/users' => ['App\Controllers\Api\UserController', 'create'],
        '/api/v1/doctors' => ['App\Controllers\Api\DoctorController', 'create'],
        '/api/v1/orders' => ['App\Controllers\Api\OrderController', 'create'],
        '/api/v1/products' => ['App\Controllers\Api\ProductController', 'create'],
        '/api/v1/leads' => ['App\Controllers\Api\LeadController', 'create']
    ]
];

// Route matching
$matched = false;
if (isset($routes[$method])) {
    foreach ($routes[$method] as $route => $handler) {
        // Exact match for now
        if ($uri === $route || $uri === rtrim($route, '/') . '/') {
            $matched = true;
            list($controller, $action) = $handler;
            
            if (class_exists($controller) && method_exists($controller, $action)) {
                $instance = new $controller();
                $instance->$action();
            } else {
                http_response_code(500);
                echo json_encode(['error' => 'Handler not implemented']);
            }
            break;
        }
    }
}

if (!$matched) {
    http_response_code(404);
    if (strpos($uri, '/api/') === 0) {
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Endpoint not found']);
    } else {
        echo "404 Not Found"; // Better to render a 404 view later
    }
}
