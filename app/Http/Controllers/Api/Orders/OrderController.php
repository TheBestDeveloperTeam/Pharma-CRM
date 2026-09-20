<?php
namespace App\Http\Controllers\Api\Orders;

use App\Core\Request;
use App\Core\Response;
use App\Domain\Orders\OrderService;
use Exception;

class OrderController {
    private OrderService $orderService;

    public function __construct(OrderService $orderService) {
        $this->orderService = $orderService;
    }

    public function index(Request $request): Response {
        $queryParams = $request->query();
        $auth = $request->getAttribute('auth_user');
        
        $filters = [
            'user_id' => $queryParams['user_id'] ?? null,
            'status' => $queryParams['status'] ?? null,
            'customer_id' => $queryParams['customer_id'] ?? null
        ];
        
        // If not admin, restrict to own orders
        if ($auth && !$auth->isAdmin()) {
            $filters['user_id'] = $auth->getId();
        }
        
        $orders = $this->orderService->listOrders($filters);
        return Response::json(['success' => true, 'data' => $orders]);
    }

    public function store(Request $request): Response {
        $auth = $request->getAttribute('auth_user');
        $userId = $auth ? $auth->getId() : null;
        $data = $request->input();
        
        if (empty($data['customer_id'])) {
            return Response::json(['success' => false, 'error' => ['message' => 'customer_id is required']], 400);
        }
        
        try {
            $order = $this->orderService->placeOrder($userId, $data);
            return Response::json(['success' => true, 'data' => $order], 201);
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
            $order = $this->orderService->getOrder((int)$id, $userId);
            
            // Access control
            if ($auth && !$auth->isAdmin() && $order['user_id'] != $userId) {
                return Response::json(['success' => false, 'error' => ['message' => 'Unauthorized']], 403);
            }
            
            return Response::json(['success' => true, 'data' => $order]);
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
        $adminId = $auth ? $auth->getId() : null;
        $data = $request->input();
        
        if (empty($data['status'])) {
            return Response::json(['success' => false, 'error' => ['message' => 'status is required']], 400);
        }
        
        try {
            if ($data['status'] === 'approved') {
                $order = $this->orderService->approveOrder((int)$id, $adminId, $data['notes'] ?? null);
            } elseif ($data['status'] === 'rejected') {
                $order = $this->orderService->rejectOrder((int)$id, $adminId, $data['notes'] ?? null);
            } else {
                throw new Exception("Invalid status update.");
            }
            
            return Response::json(['success' => true, 'data' => $order]);
        } catch (Exception $e) {
            $status = (int)$e->getCode();
            $status = $status > 0 ? $status : 500;
            if ($status < 100 || $status > 599) $status = 400;
            return Response::json(['success' => false, 'error' => ['message' => $e->getMessage()]], $status);
        }
    }
    
    public function inventory(Request $request): Response {
        $inv = $this->orderService->getInventoryList();
        return Response::json(['success' => true, 'data' => $inv]);
    }
}
