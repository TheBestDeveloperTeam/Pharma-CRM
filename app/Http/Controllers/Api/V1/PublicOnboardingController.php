<?php
declare(strict_types=1);
namespace App\Http\Controllers\Api\V1;

use App\Core\Request;
use App\Core\Response;
use App\Core\Validation;
use App\Domain\Onboarding\OnboardingService;

final class PublicOnboardingController
{
    public function __construct(private OnboardingService $onboardingService) {}

    public function register(Request $r): Response
    {
        $clean = Validation::validate($r->all(), [
            'token'     => 'required|string',
            'firm_name' => 'required|string',
            'email'     => 'required|email',
            'password'  => 'required|string|min:8',
        ]);

        $res = $this->onboardingService->completeRegistration($clean['token'], $r->all());
        return Response::json(['data' => $res], 201);
    }
}
