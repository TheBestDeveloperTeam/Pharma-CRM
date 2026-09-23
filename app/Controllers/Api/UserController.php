<?php
namespace App\Controllers\Api;

use App\Repositories\DB;
use App\Middleware\AuthMiddleware;
use PDO;

class UserController
{
    public function __construct()
    {
        AuthMiddleware::authenticateApi();
        AuthMiddleware::authorizeRole('admin');
    }

    public function index()
    {
        $tenantId = $_ENV['current_tenant_id'];
        $stmt = DB::query("SELECT id, first_name, last_name, email, role_id, status FROM users WHERE tenant_id = ?", [$tenantId]);
        $users = $stmt->fetchAll();

        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'data' => $users
        ]);
    }

    public function create()
    {
        $tenantId = $_ENV['current_tenant_id'];
        $data = json_decode(file_get_contents('php://input'), true);
        
        $firstName = htmlspecialchars(trim($data['first_name'] ?? ''));
        $email = strtolower(trim($data['email'] ?? ''));
        $password = $data['password'] ?? '';
        $roleId = (int)($data['role_id'] ?? 0);

        if (!$firstName || !$email || !$password) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Missing required fields']);
            return;
        }

        $hash = password_hash($password, PASSWORD_ARGON2ID);

        try {
            DB::query("INSERT INTO users (tenant_id, first_name, email, password_hash, role_id, status) VALUES (?, ?, ?, ?, ?, 'active')", 
                [$tenantId, $firstName, $email, $hash, $roleId]);
            
            echo json_encode(['success' => true, 'message' => 'User created']);
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Failed to create user']);
        }
    }
}
