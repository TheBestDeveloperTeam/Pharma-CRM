<?php
declare(strict_types=1);
namespace App\Repositories\Contracts;

interface IdempotencyRepositoryInterface
{
    public function find(string $franchiseRef, string $key): ?array;
    public function lock(string $orgRef, string $franchiseRef, string $key, string $method, string $path, string $requestHash): bool;
    public function complete(string $franchiseRef, string $key, int $status, array $responseJson): bool;
    public function fail(string $franchiseRef, string $key): bool;
}
