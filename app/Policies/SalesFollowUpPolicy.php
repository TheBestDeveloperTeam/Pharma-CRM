<?php
declare(strict_types=1);
namespace App\Policies;

use App\Core\Container;
use App\Core\TenantContext;
use App\Domain\Authorization\CrmScopePolicy;

/**
 * B1 — record/list scope for follow-ups from the user's `followUps` data
 * scope, no longer from users.role. Permissions are checked by the caller.
 */
final class SalesFollowUpPolicy
{
    private static function scope(): CrmScopePolicy
    {
        return Container::getInstance()->make(CrmScopePolicy::class);
    }

    public static function canView(TenantContext $ctx, array $followUp): bool
    {
        return self::scope()->canAccessFollowUp($ctx, $followUp);
    }

    public static function canUpdate(TenantContext $ctx, array $followUp): bool
    {
        return self::canView($ctx, $followUp);
    }

    /** @param array<string,mixed> $params */
    public static function listClause(TenantContext $ctx, array &$params): string
    {
        return self::scope()->followUpListClause($ctx, $params);
    }
}
