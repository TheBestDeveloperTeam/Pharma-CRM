<?php
declare(strict_types=1);
namespace App\Policies;

use App\Core\TenantContext;

final class SuperOrganizationPolicy extends Policy
{
    public function can(string $ability, mixed $subject = null): bool
    {
        return $this->ctx->isSuper();
    }
}
