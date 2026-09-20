<?php
namespace App\Repositories\Contracts;

interface DcrRepositoryInterface {
    public function createDcr(array $data): int;
    public function getDcrById(int $id): ?array;
    public function updateDcrStatus(int $id, string $status, ?string $managerNotes = null): bool;
    public function listDcrs(int $userId, string $date = null, string $status = null, int $limit = 50, int $offset = 0): array;
    
    public function addVisit(int $dcrId, array $visitData): int;
    public function getVisitById(int $id): ?array;
    public function listVisitsByDcr(int $dcrId): array;
    
    public function addProductsToVisit(int $visitId, array $productsData): void;
    
    public function getDcrByDateAndUser(string $date, int $userId): ?array;
}
