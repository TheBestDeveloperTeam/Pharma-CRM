<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

interface TokenRepositoryInterface
{
    public function store(int $userId, string $token, int $expiresAt): void;
    public function isValid(string $token): bool;
    public function revoke(string $token): void;
    public function revokeAllForUser(int $userId): void;
    public function cleanExpired(): void;
}
