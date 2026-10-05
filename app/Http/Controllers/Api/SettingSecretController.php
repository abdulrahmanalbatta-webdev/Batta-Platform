<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Support\PlatformSettings;
use Illuminate\Http\Response;

class SettingSecretController extends Controller
{
    /**
     * Remove a stored secret, e.g. an API key that was revoked.
     */
    public function destroy(string $key, PlatformSettings $settings): Response
    {
        abort_unless(PlatformSettings::definitions()[$key]['secret'] ?? false, 404);

        $settings->clear($key);

        return response()->noContent();
    }
}
