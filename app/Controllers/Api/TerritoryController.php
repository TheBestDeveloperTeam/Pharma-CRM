<?php
namespace App\Controllers\Api;

use App\Repositories\DB;
use App\Middleware\AuthMiddleware;

class TerritoryController
{
    public function index()
    {
        $payload = AuthMiddleware::authenticateApi();
        $tenantId = $payload['tenant'] ?? 0;

        $territories = DB::query("SELECT * FROM territories WHERE tenant_id = ?", [$tenantId])->fetchAll();

        header('Content-Type: application/json');
        echo json_encode([
            'status' => 'success',
            'data' => $territories
        ]);
    }

    public function create()
    {
        $payload = AuthMiddleware::authenticateApi();
        $tenantId = $payload['tenant'] ?? 0;
        
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (empty($input['name'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Missing territory name']);
            return;
        }

        DB::query(
            "INSERT INTO territories (tenant_id, name, region_code) VALUES (?, ?, ?)",
            [$tenantId, $input['name'], $input['region_code'] ?? null]
        );
        
        $newId = DB::getInstance()->lastInsertId();
        
        DB::logAudit('CREATE', 'territories', $newId, null, $input);

        http_response_code(201);
        header('Content-Type: application/json');
        echo json_encode(['status' => 'success', 'message' => 'Territory created', 'id' => $newId]);
    }
}
