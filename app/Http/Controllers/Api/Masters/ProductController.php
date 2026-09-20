<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Masters;

use App\Core\Request;
use App\Core\Response;
use App\Domain\Masters\ProductService;

class ProductController
{
    public function __construct(private readonly ProductService $products) {}

    public function index(Request $request): Response
    {
        $queryParams = $request->query();
        $page = (int) ($queryParams['page'] ?? 1);
        $perPage = (int) ($queryParams['per_page'] ?? 15);

        $filters = [
            'status'   => $queryParams['status'] ?? null,
            'category' => $queryParams['category'] ?? null,
            'search'   => $queryParams['search'] ?? null,
        ];

        $result = $this->products->paginate($filters, $page, $perPage);

        return Response::json([
            'success' => true,
            'data'    => $result['data'],
            'meta'    => $result['meta'],
        ]);
    }

    public function show(Request $request, string $id): Response
    {
        $product = $this->products->getById((int) $id);
        return Response::json(['success' => true, 'data' => $product]);
    }

    public function create(Request $request): Response
    {
        $data = $request->input();
        $actor = $request->getAttribute('auth_user');

        $id = $this->products->create($data, $actor);

        return Response::json([
            'success' => true,
            'message' => 'Product created successfully.',
            'data'    => ['id' => $id]
        ], 201);
    }

    public function update(Request $request, string $id): Response
    {
        $data = $request->input();
        $actor = $request->getAttribute('auth_user');

        $this->products->update((int) $id, $data, $actor);

        return Response::json([
            'success' => true,
            'message' => 'Product updated successfully.'
        ]);
    }
}
