<?php

declare(strict_types=1);

namespace App\Domain\Masters;

use App\Repositories\Contracts\ProductRepositoryInterface;
use App\Core\Exceptions\ValidationException;
use App\Core\Exceptions\NotFoundException;
use App\Core\Exceptions\ConflictException;
use App\Domain\Audit\AuditService;
use App\Domain\Users\AuthUser;

class ProductService
{
    public function __construct(
        private readonly ProductRepositoryInterface $products,
        private readonly AuditService               $audit
    ) {}

    public function paginate(array $filters, int $page = 1, int $perPage = 15): array
    {
        return $this->products->paginate($filters, max(1, $page), max(1, $perPage));
    }

    public function getById(int $id): array
    {
        $product = $this->products->findById($id);
        if (!$product) {
            throw new NotFoundException("Product not found.");
        }
        return $product;
    }

    public function create(array $data, AuthUser $actor): int|string
    {
        if (empty($data['name']) || empty($data['sku_code'])) {
            throw new ValidationException(['general' => "Name and SKU code are required."]);
        }

        if ($this->products->findBySku($data['sku_code'])) {
            throw new ConflictException("SKU code already exists.");
        }

        $insertData = [
            'sku_code'   => trim($data['sku_code']),
            'name'       => trim($data['name']),
            'category'   => $data['category'] ?? null,
            'price'      => (float) ($data['price'] ?? 0.0),
            'status'     => $data['status'] ?? 'active',
            'created_by' => $actor->getId(),
        ];

        $id = $this->products->create($insertData);
        $this->audit->log('product.created', 'product', (int) $id, null, $insertData, ['actor' => $actor->getId()]);

        return $id;
    }

    public function update(int $id, array $data, AuthUser $actor): void
    {
        $old = $this->getById($id);

        $updateData = [];
        if (isset($data['name'])) {
            $updateData['name'] = trim($data['name']);
        }
        if (isset($data['category'])) {
            $updateData['category'] = trim($data['category']);
        }
        if (isset($data['price'])) {
            $updateData['price'] = (float) $data['price'];
        }
        if (isset($data['status'])) {
            $updateData['status'] = $data['status'];
        }
        
        $updateData['updated_by'] = $actor->getId();
        $updateData['updated_at'] = date('Y-m-d H:i:s');

        $this->products->update($id, $updateData);
        $new = $this->getById($id);

        $this->audit->log('product.updated', 'product', $id, $old, $new, ['actor' => $actor->getId()]);
    }
}
