<?php
declare(strict_types=1);
namespace App\Http\Controllers\Api\V1;
use App\Core\{Request,Response}; use App\Domain\Onboarding\OnboardingService;
final class PublicOnboardingController { public function __construct(private OnboardingService $service){} public function register(Request $r):Response{return Response::json(201,$this->service->register((string)$r->input('token'),$r->all()));} }
