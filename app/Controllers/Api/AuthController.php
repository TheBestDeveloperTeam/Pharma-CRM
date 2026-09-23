<?php
namespace App\Controllers\Api;

use App\Repositories\DB;
use App\Utils\JwtAuth;
use PDO;

class AuthController
{
    public function token()
    {
        $data = json_decode(file_get_contents('php://input'), true);
        $email = strtolower(trim($data['email'] ?? ''));
        $password = $data['password'] ?? '';

        if (!$email || !$password) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Missing email or password']);
            return;
        }

        // We assume global users table but need to know which tenant they belong to
        // If a user belongs to a tenant, they log in. 
        $stmt = DB::query("SELECT id, tenant_id, password_hash, role_id, status FROM users WHERE email = ?", [$email]);
        $user = $stmt->fetch();

        if (!$user || $user['status'] !== 'active') {
            http_response_code(401);
            echo json_encode(['success' => false, 'error' => 'Invalid credentials or inactive account']);
            return;
        }

        if (password_verify($password, $user['password_hash'])) {
            // Fetch roles/permissions
            $roles = [];
            if ($user['role_id']) {
                $roleStmt = DB::query("SELECT name FROM roles WHERE id = ?", [$user['role_id']]);
                $role = $roleStmt->fetchColumn();
                if ($role) $roles[] = $role;
            }

            $payload = [
                'sub' => $user['id'],
                'tenant' => $user['tenant_id'],
                'roles' => $roles
            ];

            $token = JwtAuth::generateToken($payload, 3600); // 1 hour token

            echo json_encode([
                'success' => true,
                'data' => [
                    'access_token' => $token,
                    'token_type' => 'Bearer',
                    'expires_in' => 3600
                ]
            ]);
        } else {
            http_response_code(401);
            echo json_encode(['success' => false, 'error' => 'Invalid credentials']);
        }
    }
}
