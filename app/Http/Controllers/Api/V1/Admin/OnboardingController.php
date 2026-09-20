<?php
declare(strict_types=1);
namespace App\Http\Controllers\Api\V1\Admin;

use App\Core\Request;
use App\Core\Response;
use App\Core\Validation;
use App\Core\TenantContext;
use App\Domain\Onboarding\OnboardingService;

final class OnboardingController
{
    public function __construct(private OnboardingService $onboardingService) {}

    public function invite(Request $r): Response
    {
        $ctx = TenantContext::get();
        $leadRef = $r->input('lead_ref');

        $invite = $this->onboardingService->issueInvite(
            $ctx->orgRef,
            $ctx->franchiseRef,
            $leadRef,
            $ctx->userRef
        );

        return Response::json(['data' => $invite], 201);
    }
}
