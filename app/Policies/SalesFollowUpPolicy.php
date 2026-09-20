<?php
declare(strict_types=1);
namespace App\Policies;

use App\Core\TenantContext;

final class SalesFollowUpPolicy
{
    public static function canView(TenantContext $ctx, array $followup): bool
    {
        if ($ctx->isSuperAdmin() || $ctx->isFranchiseAdmin()) {
            return true;
        }

        if ($ctx->role === 'SALES') {
            return $followup['assigned_user_ref'] === $ctx->userRef;
        }

        return false;
    }

    public static function canUpdate(TenantContext $ctx, array $followup): bool
    {
        return self::canView($ctx, $followup);
    }
}
