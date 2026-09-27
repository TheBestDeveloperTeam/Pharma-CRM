<?php
declare(strict_types=1);
namespace App\Policies;

use App\Core\Container;
use App\Core\TenantContext;
use App\Domain\Authorization\CrmScopePolicy;

/**
 * B1 — record/list scope for leads from the user's `leads` data scope
 * (ALL / TERRITORY / TEAM / OWN / NONE), no longer from users.role.
 * The permission itself (leads.view / leads.edit) is checked by the caller.
 */
final class SalesLeadPolicy
{
    private static function scope(): CrmScopePolicy
    {
        return Container::getInstance()->make(CrmScopePolicy::class);
    }

    public static function canView(TenantContext $ctx, array $lead): bool
    {
        return self::scope()->canAccessLead($ctx, $lead);
    }

    public static function canUpdate(TenantContext $ctx, array $lead): bool
    {
        return self::canView($ctx, $lead);
    }

    /** @param array<string,mixed> $params */
    public static function listClause(TenantContext $ctx, array &$params): string
    {
        return self::scope()->leadListClause($ctx, $params);
    }
}
