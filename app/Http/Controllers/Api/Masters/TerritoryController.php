<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Masters;

use App\Core\Request;
use App\Core\Response;
use App\Domain\Masters\TerritoryService;

class TerritoryController
{
    public function __construct(private readonly TerritoryService $territories) {}

    public function index(Request $request): Response
    {
        $queryParams = $request->query();
        
        if (isset($queryParams['tree']) && $queryParams['tree'] === '1') {
            $data = $this->territories->getTree();
            return Response::json(['success' => true, 'data' => $data]);
        }

        $page = (int) ($queryParams['page'] ?? 1);
        $perPage = (int) ($queryParams['per_page'] ?? 15);

        $filters = [
            'status' => $queryParams['status'] ?? null,
            'type'   => $queryParams['type'] ?? null,
            'search' => $queryParams['search'] ?? null,
        ];

        $result = $this->territories->paginate($filters, $page, $perPage);

        return Response::json([
            'success' => true,
            'data'    => $result['data'],
            'meta'    => $result['meta'],
        ]);
    }

    public function show(Request $request, string $id): Response
    {
        $territory = $this->territories->getById((int) $id);
        return Response::json(['success' => true, 'data' => $territory]);
    }

    public function create(Request $request): Response
    {
        $data = $request->input();
        $actor = $request->getAttribute('auth_user');

        $id = $this->territories->create($data, $actor);

        return Response::json([
            'success' => true,
            'message' => 'Territory created successfully.',
            'data'    => ['id' => $id]
        ], 201);
    }

    public function update(Request $request, string $id): Response
    {
        $data = $request->input();
        $actor = $request->getAttribute('auth_user');

        $this->territories->update((int) $id, $data, $actor);

        return Response::json([
            'success' => true,
            'message' => 'Territory updated successfully.'
        ]);
    }
}
