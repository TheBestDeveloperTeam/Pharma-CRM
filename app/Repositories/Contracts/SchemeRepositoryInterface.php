<?php
declare(strict_types=1);
namespace App\Repositories\Contracts;

interface SchemeRepositoryInterface
{
    public function list(string $franchiseRef, array $filters, int $page, int $perPage): array;
    public function findByRef(string $franchiseRef, string $schemeRef): ?array;
    public function findRules(string $franchiseRef, string $schemeRef): array;
    public function findApplicableRules(string $franchiseRef, string $productRef, ?string $tierRef, string $date): array;
    public function createScheme(array $data): string;
    public function createRule(array $data): string;
    public function updateScheme(string $franchiseRef, string $schemeRef, array $data): bool;
}
