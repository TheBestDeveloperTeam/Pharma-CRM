<?php
declare(strict_types=1);
namespace App\Repositories\Contracts;

interface SystemSettingsRepositoryInterface
{
    public function list(string $franchiseRef): array;
    public function get(string $franchiseRef, string $key, ?string $default = null): ?string;
    public function set(string $orgRef, string $franchiseRef, string $key, string $value, string $actorRef): bool;
}
