<?php
namespace App\Controllers\Api;

use App\Repositories\DB;
use App\Middleware\AuthMiddleware;
use PDO;

class DoctorController
{
    public function __construct()
    {
        AuthMiddleware::authenticateApi();
    }

    public function index()
    {
        $tenantId = $_ENV['current_tenant_id'];
        $stmt = DB::query("SELECT id, first_name, last_name, email, phone, license_no, status FROM doctors WHERE tenant_id = ?", [$tenantId]);
        $doctors = $stmt->fetchAll();

        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'data' => $doctors]);
    }

    public function create()
    {
        $tenantId = $_ENV['current_tenant_id'];
        $data = json_decode(file_get_contents('php://input'), true);
        
        $firstName = htmlspecialchars(trim($data['first_name'] ?? ''));
        $lastName = htmlspecialchars(trim($data['last_name'] ?? ''));
        $email = strtolower(trim($data['email'] ?? ''));
        $phone = htmlspecialchars(trim($data['phone'] ?? ''));
        $licenseNo = htmlspecialchars(trim($data['license_no'] ?? ''));

        if (!$firstName || !$licenseNo) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Missing required fields']);
            return;
        }

        try {
            DB::query("INSERT INTO doctors (tenant_id, first_name, last_name, email, phone, license_no, status) VALUES (?, ?, ?, ?, ?, ?, 'verified')", 
                [$tenantId, $firstName, $lastName, $email, $phone, $licenseNo]);
            
            echo json_encode(['success' => true, 'message' => 'Doctor created']);
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Failed to create doctor']);
        }
    }
}
