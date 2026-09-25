<?php
declare(strict_types=1);
namespace App\Domain\Onboarding;

use App\Core\Exceptions\ValidationException;

final class OnboardingLifecycle
{
    private const NEXT = [
        'SUBMITTED' => ['INFO_REQUESTED', 'APPROVED', 'REJECTED'],
        'INFO_REQUESTED' => ['SUBMITTED', 'REJECTED'],
        'APPROVED' => [], 'REJECTED' => [],
    ];

    public static function assertTransition(string $from, string $to): void
    {
        if (!in_array($to, self::NEXT[$from] ?? [], true)) {
            throw new ValidationException('INVALID_ONBOARDING_TRANSITION', "Cannot transition onboarding from {$from} to {$to}.");
        }
    }
}
