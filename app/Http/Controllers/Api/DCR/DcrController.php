<?php
namespace App\Http\Controllers\Api\DCR;

use App\Core\Request;
use App\Core\Response;
use App\Domain\DCR\DcrService;
use Exception;

class DcrController {
    private DcrService $dcrService;

    public function __construct(DcrService $dcrService) {
        $this->dcrService = $dcrService;
    }

    public function index(Request $request): Response {
        $queryParams = $request->query();
        $filters = [
            'user_id' => $queryParams['user_id'] ?? null,
            'date' => $queryParams['date'] ?? null,
            'status' => $queryParams['status'] ?? null
        ];
        
        $auth = $request->getAttribute('auth_user');
        $userId = !empty($filters['user_id']) ? $filters['user_id'] : ($auth ? $auth->getId() : 0);
        $dcrs = $this->dcrService->listUserDcrs((int)$userId, $filters['date'], $filters['status']);
        return Response::json(['success' => true, 'data' => $dcrs]);
    }

    public function store(Request $request): Response {
        $auth = $request->getAttribute('auth_user');
        $userId = $auth ? $auth->getId() : null;
        $data = $request->input();
        
        if (empty($data['territory_id']) || empty($data['dcr_date'])) {
            return Response::json(['success' => false, 'error' => ['message' => 'territory_id and dcr_date are required']], 400);
        }
        
        try {
            $dcr = $this->dcrService->createDcr($userId, $data);
            return Response::json(['success' => true, 'data' => $dcr], 201);
        } catch (Exception $e) {
            $status = (int)$e->getCode();
            $status = $status > 0 ? $status : 500;
            if ($status < 100 || $status > 599) $status = 400;
            return Response::json(['success' => false, 'error' => ['message' => $e->getMessage()]], $status);
        }
    }

    public function show(Request $request): Response {
        $id = $request->param('id');
        $auth = $request->getAttribute('auth_user');
        $userId = $auth ? $auth->getId() : null;
        
        try {
            $dcr = $this->dcrService->getDcrWithVisits((int)$id, $userId);
            
            // Access control: Only admin or owner can view
            if ($auth && !$auth->isAdmin() && $dcr['user_id'] != $userId) {
                return Response::json(['success' => false, 'error' => ['message' => 'Unauthorized']], 403);
            }
            
            return Response::json(['success' => true, 'data' => $dcr]);
        } catch (Exception $e) {
            $status = (int)$e->getCode();
            $status = $status > 0 ? $status : 500;
            if ($status < 100 || $status > 599) $status = 400;
            return Response::json(['success' => false, 'error' => ['message' => $e->getMessage()]], $status);
        }
    }

    public function addVisit(Request $request): Response {
        $id = $request->param('id');
        $auth = $request->getAttribute('auth_user');
        $userId = $auth ? $auth->getId() : null;
        $data = $request->input();
        
        if (empty($data['customer_id'])) {
            return Response::json(['success' => false, 'error' => ['message' => 'customer_id is required']], 400);
        }
        
        try {
            $visit = $this->dcrService->addVisit((int)$id, $userId, $data);
            return Response::json(['success' => true, 'data' => $visit], 201);
        } catch (Exception $e) {
            $status = (int)$e->getCode();
            $status = $status > 0 ? $status : 500;
            if ($status < 100 || $status > 599) $status = 400;
            return Response::json(['success' => false, 'error' => ['message' => $e->getMessage()]], $status);
        }
    }

    public function updateStatus(Request $request): Response {
        $id = $request->param('id');
        $auth = $request->getAttribute('auth_user');
        $userId = $auth ? $auth->getId() : null;
        $data = $request->input();
        
        if (empty($data['status'])) {
            return Response::json(['success' => false, 'error' => ['message' => 'status is required']], 400);
        }
        
        try {
            $notes = $data['notes'] ?? null;
            $dcr = $this->dcrService->updateStatus((int)$id, $userId, $data['status'], $notes);
            
            return Response::json(['success' => true, 'data' => $dcr]);
        } catch (Exception $e) {
            $status = (int)$e->getCode();
            $status = $status > 0 ? $status : 500;
            if ($status < 100 || $status > 599) $status = 400;
            return Response::json(['success' => false, 'error' => ['message' => $e->getMessage()]], $status);
        }
    }
}