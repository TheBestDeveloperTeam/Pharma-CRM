<?php
declare(strict_types=1);
namespace App\Repositories\Contracts;

interface NotificationTemplateRepositoryInterface
{
    public function list(string $franchiseRef): array;
    public function find(string $franchiseRef, string $eventType, string $channel): ?array;
    public function save(array $data): bool;
}
