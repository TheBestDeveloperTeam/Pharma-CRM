<?php
declare(strict_types=1);
namespace App\Domain\Orders;

use App\Core\Exceptions\ValidationException;

final class OrderStateMachine
{
    private const VALID_TRANSITIONS = [
        'DRAFT'            => ['SUBMITTED', 'CANCELLED'],
        'SUBMITTED'        => ['CONFIRMED', 'CANCELLED'],
        // PROCESSING, DISPATCHED and DELIVERED are canonical lifecycle
        // states. TASK-005 deliberately owns no transition into the latter
        // two: Dispatch and Delivery will add those transitions later.
        'CONFIRMED'        => ['PROCESSING', 'CANCELLED'],
        'PROCESSING'       => ['CANCELLED'],
        'DISPATCHED'       => [],
        'DELIVERED'        => [],
        'CANCELLED'        => [],
    ];

    public static function canTransition(string $fromStatus, string $toStatus): bool
    {
        $allowed = self::VALID_TRANSITIONS[$fromStatus] ?? [];
        return in_array($toStatus, $allowed, true);
    }

    public static function assertCanTransition(string $fromStatus, string $toStatus): void
    {
        if (!self::canTransition($fromStatus, $toStatus)) {
            throw new ValidationException(
                'INVALID_ORDER_TRANSITION',
                "Order cannot transition from {$fromStatus} to {$toStatus}."
            );
        }
    }
}
