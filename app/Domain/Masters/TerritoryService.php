<?php

declare(strict_types=1);

namespace App\Domain\Masters;

use App\Repositories\Contracts\TerritoryRepositoryInterface;
use App\Core\Exceptions\ValidationException;
use App\Core\Exceptions\NotFoundException;
use App\Domain\Audit\AuditService;
use App\Domain\Users\AuthUser;

class TerritoryService
{
    public function __construct(
        private readonly TerritoryRepositoryInterface $territories,
        private readonly AuditService                 $audit
    ) {}

    public function getTree(): array
    {
        return $this->territories->getTree();
    }

    public function paginate(array $filters, int $page = 1, int $perPage = 15): array
    {
        return $this->territories->paginate($filters, max(1, $page), max(1, $perPage));
    }

    public function getById(int $id): array
    {
        $territory = $this->territories->findById($id);
        if (!$territory) {
            throw new NotFoundException("Territory not found.");
        }
        return $territory;
    }

    public function create(array $data, AuthUser $actor): int|string
    {
        if (empty($data['name']) || empty($data['type'])) {
            throw new ValidationException(['general' => "Name and type are required."]);
        }

        $validTypes = ['zone', 'region', 'hq', 'territory'];
        if (!in_array($data['type'], $validTypes, true)) {
            throw new ValidationException(['general' => "Invalid territory type."]);
        }

        $insertData = [
            'name'       => trim($data['name']),
            'type'       => $data['type'],
            'parent_id'  => $data['parent_id'] ?? null,
            'status'     => $data['status'] ?? 'active',
            'created_by' => $actor->getId(),
        ];

        $id = $this->territories->create($insertData);
        $this->audit->log('territory.created', 'territory', (int) $id, null, $insertData, ['actor' => $actor->getId()]);

        return $id;
    }

    public function update(int $id, array $data, AuthUser $actor): void
    {
        $old = $this->getById($id);

        $updateData = [];
        if (isset($data['name'])) {
            $updateData['name'] = trim($data['name']);
        }
        if (isset($data['status'])) {
            $updateData['status'] = $data['status'];
        }
        if (isset($data['parent_id'])) {
            $updateData['parent_id'] = $data['parent_id'];
        }
        $updateData['updated_by'] = $actor->getId();
        $updateData['updated_at'] = date('Y-m-d H:i:s');

        $this->territories->update($id, $updateData);
        $new = $this->getById($id);

        $this->audit->log('territory.updated', 'territory', $id, $old, $new, ['actor' => $actor->getId()]);
    }
}
