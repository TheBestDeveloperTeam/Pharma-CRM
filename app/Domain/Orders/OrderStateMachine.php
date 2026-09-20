<?php
declare(strict_types=1);
namespace App\Domain\Orders;

use App\Core\Exceptions\ValidationException;

final class OrderStateMachine
{
    private const VALID_TRANSITIONS = [
        'DRAFT'            => ['SUBMITTED', 'CANCELLED'],
        'SUBMITTED'        => ['UNDER_REVIEW', 'CONFIRMED', 'ON_HOLD', 'REJECTED', 'CANCELLED'],
        'UNDER_REVIEW'     => ['CONFIRMED', 'ON_HOLD', 'REJECTED', 'CANCELLED'],
        'ON_HOLD'          => ['UNDER_REVIEW', 'CONFIRMED', 'REJECTED', 'CANCELLED'],
        'CONFIRMED'        => ['BILLING_PENDING', 'CANCELLED'],
        'BILLING_PENDING'  => ['BILLED', 'CANCELLED'],
        'BILLED'           => ['PACKED', 'CANCELLED'],
        'PACKED'           => ['DISPATCH_READY', 'CANCELLED'],
        'DISPATCH_READY'   => ['DISPATCHED', 'CANCELLED'],
        'DISPATCHED'       => ['DELIVERED', 'CANCELLED'],
        'DELIVERED'        => ['COMPLETED'],
        'COMPLETED'        => [],
        'CANCELLED'        => [],
        'REJECTED'         => [],
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
