<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Support\PlatformSettings;
use Illuminate\Http\JsonResponse;

class SiteSettingsController extends Controller
{
    /**
     * The settings the public site reads (no sign-in): name, contact details, maintenance and sign-up switches,
     * currency and tax, and the payment methods that are ready with their public keys. Never any secret.
     */
    public function __invoke(PlatformSettings $settings): JsonResponse
    {
        return response()->json(['data' => $settings->forPublic()]);
    }
}
