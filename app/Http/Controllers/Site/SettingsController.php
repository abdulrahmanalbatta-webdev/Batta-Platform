<?php

namespace App\Http\Controllers\Site;

use App\Enums\LeadService;
use App\Http\Controllers\Controller;
use App\Support\PlatformSettings;
use Illuminate\Http\JsonResponse;

class SettingsController extends Controller
{
    /**
     * What the public site needs before it renders: name, contact details, maintenance and sign-up switches,
     * currency and tax, the payment methods that are ready with their public keys, and the services offered
     * on the project request form. Never any secret.
     */
    public function __invoke(PlatformSettings $settings): JsonResponse
    {
        return response()->json(['data' => [
            ...$settings->forPublic(),
            'project_services' => collect(LeadService::cases())->map(fn (LeadService $service): array => [
                'value' => $service->value,
                'label' => $service->label(),
            ])->all(),
        ]]);
    }
}
