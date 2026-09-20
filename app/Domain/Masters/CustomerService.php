<?php

declare(strict_types=1);

namespace App\Domain\Masters;

use App\Repositories\Contracts\CustomerRepositoryInterface;
use App\Core\Exceptions\ValidationException;
use App\Core\Exceptions\NotFoundException;
use App\Domain\Audit\AuditService;
use App\Domain\Users\AuthUser;

class CustomerService
{
    public function __construct(
        private readonly CustomerRepositoryInterface $customers,
        private readonly AuditService                $audit
    ) {}

    public function paginate(array $filters, int $page = 1, int $perPage = 15): array
    {
        return $this->customers->paginate($filters, max(1, $page), max(1, $perPage));
    }

    public function getById(int $id): array
    {
        $customer = $this->customers->findById($id);
        if (!$customer) {
            throw new NotFoundException("Customer not found.");
        }
        return $customer;
    }

    public function create(array $data, AuthUser $actor): int|string
    {
        if (empty($data['name']) || empty($data['type']) || empty($data['territory_id'])) {
            throw new ValidationException(['general' => "Name, type, and territory_id are required."]);
        }

        $validTypes = ['doctor', 'chemist', 'stockist'];
        if (!in_array($data['type'], $validTypes, true)) {
            throw new ValidationException(['general' => "Invalid customer type."]);
        }

        $insertData = [
            'name'         => trim($data['name']),
            'type'         => $data['type'],
            'email'        => $data['email'] ?? null,
            'phone'        => $data['phone'] ?? null,
            'specialty'    => $data['specialty'] ?? null,
            'territory_id' => (int) $data['territory_id'],
            'status'       => $data['status'] ?? 'active',
            'created_by'   => $actor->getId(),
        ];

        $id = $this->customers->create($insertData);
        $this->audit->log('customer.created', 'customer', (int) $id, null, $insertData, ['actor' => $actor->getId()]);

        return $id;
    }

    public function update(int $id, array $data, AuthUser $actor): void
    {
        $old = $this->getById($id);

        $updateData = [];
        if (isset($data['name'])) {
            $updateData['name'] = trim($data['name']);
        }
        if (isset($data['email'])) {
            $updateData['email'] = trim($data['email']);
        }
        if (isset($data['phone'])) {
            $updateData['phone'] = trim($data['phone']);
        }
        if (isset($data['specialty'])) {
            $updateData['specialty'] = trim($data['specialty']);
        }
        if (isset($data['territory_id'])) {
            $updateData['territory_id'] = (int) $data['territory_id'];
        }
        if (isset($data['status'])) {
            $updateData['status'] = $data['status'];
        }
        
        $updateData['updated_by'] = $actor->getId();
        $updateData['updated_at'] = date('Y-m-d H:i:s');

        $this->customers->update($id, $updateData);
        $new = $this->getById($id);

        $this->audit->log('customer.updated', 'customer', $id, $old, $new, ['actor' => $actor->getId()]);
    }
}
