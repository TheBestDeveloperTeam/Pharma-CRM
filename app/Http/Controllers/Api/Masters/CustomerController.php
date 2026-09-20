<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Masters;

use App\Core\Request;
use App\Core\Response;
use App\Domain\Masters\CustomerService;

class CustomerController
{
    public function __construct(private readonly CustomerService $customers) {}

    public function index(Request $request): Response
    {
        $queryParams = $request->query();
        $page = (int) ($queryParams['page'] ?? 1);
        $perPage = (int) ($queryParams['per_page'] ?? 15);

        $filters = [
            'status'       => $queryParams['status'] ?? null,
            'type'         => $queryParams['type'] ?? null,
            'territory_id' => $queryParams['territory_id'] ?? null,
            'search'       => $queryParams['search'] ?? null,
        ];

        $result = $this->customers->paginate($filters, $page, $perPage);

        return Response::json([
            'success' => true,
            'data'    => $result['data'],
            'meta'    => $result['meta'],
        ]);
    }

    public function show(Request $request, string $id): Response
    {
        $customer = $this->customers->getById((int) $id);
        return Response::json(['success' => true, 'data' => $customer]);
    }

    public function create(Request $request): Response
    {
        $data = $request->input();
        $actor = $request->getAttribute('auth_user');

        $id = $this->customers->create($data, $actor);

        return Response::json([
            'success' => true,
            'message' => 'Customer created successfully.',
            'data'    => ['id' => $id]
        ], 201);
    }

    public function update(Request $request, string $id): Response
    {
        $data = $request->input();
        $actor = $request->getAttribute('auth_user');

        $this->customers->update((int) $id, $data, $actor);

        return Response::json([
            'success' => true,
            'message' => 'Customer updated successfully.'
        ]);
    }
}
