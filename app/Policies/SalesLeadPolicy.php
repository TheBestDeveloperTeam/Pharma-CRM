<?php
declare(strict_types=1);
namespace App\Policies;

use App\Core\TenantContext;

final class SalesLeadPolicy
{
    public static function canView(TenantContext $ctx, array $lead): bool
    {
        if ($ctx->isSuperAdmin() || $ctx->isFranchiseAdmin()) {
            return true;
        }

        if ($ctx->role === 'SALES') {
            return $lead['assigned_user_ref'] === $ctx->userRef;
        }

        return false;
    }

    public static function canUpdate(TenantContext $ctx, array $lead): bool
    {
        return self::canView($ctx, $lead);
    }
}
