<?php
declare(strict_types=1);
namespace App\Policies;

use App\Core\TenantContext;
use App\Core\Exceptions\ForbiddenException;

abstract class Policy
{
    public function __construct(protected TenantContext $ctx) {}

    abstract public function can(string $ability, mixed $subject = null): bool;

    public function authorize(string $ability, mixed $subject = null): void
    {
        if (!$this->can($ability, $subject)) {
            throw new ForbiddenException('FORBIDDEN', "Action [{$ability}] not permitted for role [{$this->ctx->role}].");
        }
    }
}
