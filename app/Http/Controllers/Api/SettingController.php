<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Activity;
use App\Support\PlatformSettings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class SettingController extends Controller
{
    /**
     * Every platform setting; secrets only say whether they're set.
     */
    public function show(Request $request, PlatformSettings $settings): JsonResponse
    {
        return $this->response($request, $settings);
    }

    /**
     * Save any subset of the settings. Leaving a secret empty keeps the stored one.
     */
    public function update(Request $request, PlatformSettings $settings): JsonResponse
    {
        $definitions = PlatformSettings::definitions();

        $ownerOnly = array_intersect(array_keys($request->all()), PlatformSettings::ownerOnlyKeys());
        abort_if($ownerOnly !== [] && $request->user()->cannot('manage-platform-data'), 403, 'إعدادات البريد للمالك فقط.');

        $validated = $request->validate(
            collect($definitions)->map(fn (array $definition): array => ['sometimes', ...$definition['rules']])->all(),
            [],
            collect($definitions)->map(fn (array $definition): string => $definition['label'])->all(),
        );

        // a new server or username drops the stored SMTP password: ask for it rather than lose it silently
        $mailServerChanges = collect(['mail_host', 'mail_username'])->contains(fn (string $key): bool => array_key_exists($key, $validated) && $validated[$key] !== $settings->get($key));
        $newHost = array_key_exists('mail_host', $validated) ? $validated['mail_host'] : $settings->get('mail_host');
        if ($mailServerChanges && filled($newHost) && blank($validated['mail_password'] ?? null)) {
            throw ValidationException::withMessages(['mail_password' => 'أدخل كلمة مرور SMTP مع تغيير الخادم أو اسم المستخدم (تُمسح المحفوظة عند تغييرهما).']);
        }

        $settings->update($validated);

        if ($validated !== []) {
            // secrets are logged by name only, like everything else here
            Activity::create(['user_id' => $request->user()->id, 'action' => 'updated', 'subject_name' => 'إعدادات المنصة', 'properties' => ['changed' => array_keys($validated)]]);
        }

        return $this->response($request, $settings);
    }

    private function response(Request $request, PlatformSettings $settings): JsonResponse
    {
        return response()->json([
            'data' => $settings->forClient(withHints: $request->user()->can('manage-platform-data')),
            'meta' => [
                'owner_only' => PlatformSettings::ownerOnlyKeys(),
                'currencies' => PlatformSettings::CURRENCIES,
                'session_lifetimes' => PlatformSettings::SESSION_LIFETIMES,
            ],
        ]);
    }
}
