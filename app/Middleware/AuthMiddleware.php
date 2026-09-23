<?php
namespace App\Middleware;

use App\Utils\JwtAuth;

class AuthMiddleware
{
    public static function authenticateApi(): array
    {
        $headers = getallheaders();
        $authHeader = $headers['Authorization'] ?? '';

        if (!$authHeader || !preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
            http_response_code(401);
            echo json_encode(['error' => 'Unauthorized: Missing or invalid Bearer token']);
            exit;
        }

        $token = $matches[1];
        $payload = JwtAuth::decodeToken($token);

        if (empty($payload)) {
            http_response_code(401);
            echo json_encode(['error' => 'Unauthorized: Invalid or expired token']);
            exit;
        }

        // Attach tenant_id to a globally accessible place or return it
        $_ENV['current_tenant_id'] = $payload['tenant'] ?? null;
        $_ENV['current_user_id'] = $payload['sub'] ?? null;
        $_ENV['current_roles'] = $payload['roles'] ?? [];

        return $payload;
    }
    
    public static function authorizeRole(string $role): void
    {
        $roles = $_ENV['current_roles'] ?? [];
        if (!in_array($role, $roles)) {
            http_response_code(403);
            echo json_encode(['error' => 'Forbidden: Insufficient privileges']);
            exit;
        }
    }
}
