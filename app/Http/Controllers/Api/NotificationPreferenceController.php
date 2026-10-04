<?php

namespace App\Http\Controllers\Api;

use App\Enums\AlertType;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationPreferenceController extends Controller
{
    /**
     * Turn the email for each alert type on or off for the signed-in member, e.g. {"orders": true, "messages": false}.
     */
    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate(collect(AlertType::cases())
            ->mapWithKeys(fn (AlertType $type): array => [$type->value => ['sometimes', 'boolean']])
            ->all());

        $user = $request->user();
        $user->forceFill(['notification_preferences' => [...($user->notification_preferences ?? []), ...array_map('boolval', $validated)]])->save();

        return response()->json(['data' => $user->emailPreferences()]);
    }
}
