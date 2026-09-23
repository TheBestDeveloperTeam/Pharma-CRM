<?php
namespace App\Controllers\Api;

use App\Repositories\DB;
use App\Middleware\AuthMiddleware;

class DoctorController
{
    public function index()
    {
        $payload = AuthMiddleware::authenticateApi();
        $tenantId = $payload['tenant'] ?? 0;

        // Fetch doctors and join territories
        $sql = "SELECT d.*, t.name as territory_name 
                FROM doctors d
                LEFT JOIN territories t ON d.territory_id = t.id
                WHERE d.tenant_id = ? AND d.deleted_at IS NULL";
        
        $doctors = DB::query($sql, [$tenantId])->fetchAll();

        header('Content-Type: application/json');
        echo json_encode([
            'status' => 'success',
            'data' => $doctors
        ]);
    }

    public function create()
    {
        $payload = AuthMiddleware::authenticateApi();
        $tenantId = $payload['tenant'] ?? 0;
        
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (empty($input['first_name'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Missing first_name']);
            return;
        }

        DB::query(
            "INSERT INTO doctors (tenant_id, first_name, last_name, email, phone, license_no, territory_id) 
             VALUES (?, ?, ?, ?, ?, ?, ?)",
            [
                $tenantId, 
                $input['first_name'], 
                $input['last_name'] ?? null, 
                $input['email'] ?? null,
                $input['phone'] ?? null,
                $input['license_no'] ?? null,
                $input['territory_id'] ?? null
            ]
        );
        
        $newId = DB::getInstance()->lastInsertId();
        
        DB::logAudit('CREATE', 'doctors', $newId, null, $input);

        http_response_code(201);
        header('Content-Type: application/json');
        echo json_encode(['status' => 'success', 'message' => 'Doctor created', 'id' => $newId]);
    }
}
