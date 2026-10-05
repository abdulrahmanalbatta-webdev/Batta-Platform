<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Activity;
use App\Support\PlatformSettings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SettingController extends Controller
{
    /**
     * Every platform setting; secrets only say whether they're set.
     */
    public function show(PlatformSettings $settings): JsonResponse
    {
        return $this->response($settings);
    }

    /**
     * Save any subset of the settings. Leaving a secret empty keeps the stored one.
     */
    public function update(Request $request, PlatformSettings $settings): JsonResponse
    {
        $definitions = PlatformSettings::definitions();

        $validated = $request->validate(
            collect($definitions)->map(fn (array $definition): array => ['sometimes', ...$definition['rules']])->all(),
            [],
            collect($definitions)->map(fn (array $definition): string => $definition['label'])->all(),
        );

        $settings->update($validated);

        if ($validated !== []) {
            // secrets are logged by name only, like everything else here
            Activity::create(['user_id' => $request->user()->id, 'action' => 'updated', 'subject_name' => 'إعدادات المنصة', 'properties' => ['changed' => array_keys($validated)]]);
        }

        return $this->response($settings);
    }

    private function response(PlatformSettings $settings): JsonResponse
    {
        return response()->json([
            'data' => $settings->forClient(),
            'meta' => [
                'gateways' => collect(['stripe', 'paypal', 'bank_transfer'])->mapWithKeys(fn (string $gateway): array => [$gateway => $settings->gatewayReady($gateway)]),
                'currencies' => PlatformSettings::CURRENCIES,
                'session_lifetimes' => PlatformSettings::SESSION_LIFETIMES,
            ],
        ]);
    }
}
