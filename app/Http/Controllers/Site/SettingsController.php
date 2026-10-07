<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Support\PlatformSettings;
use App\Support\ProjectServices;
use Illuminate\Http\JsonResponse;

class SettingsController extends Controller
{
    /**
     * What the public site needs before it renders: name, contact details, maintenance and sign-up switches,
     * the comments switch, the currency of project budgets and the services offered on the project request form.
     * Never any secret.
     */
    public function __invoke(PlatformSettings $settings, ProjectServices $services): JsonResponse
    {
        return response()->json(['data' => [
            ...$settings->forPublic(),
            'project_services' => collect($services->options())->map(fn (string $title, string $id): array => ['value' => $id, 'label' => $title])->values()->all(),
        ]]);
    }
}
