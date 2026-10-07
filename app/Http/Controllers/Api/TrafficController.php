<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Support\GoogleAnalytics;
use App\Support\GoogleAnalyticsUnavailable;
use App\Support\RegistrationsReport;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class TrafficController extends Controller
{
    /**
     * The public site's traffic from Google Analytics for the same periods as the sales report. Not connected
     * or failing, it answers 200 with meta.configured / meta.error so the page shows why instead of an error.
     */
    public function __invoke(Request $request, GoogleAnalytics $analytics): JsonResponse
    {
        $validated = $request->validate(['days' => ['sometimes', 'integer', Rule::in(RegistrationsReport::PERIODS)]]);

        if (! $analytics->configured()) {
            return response()->json(['data' => null, 'meta' => ['configured' => false, 'error' => null]]);
        }

        try {
            return response()->json(['data' => $analytics->report((int) ($validated['days'] ?? 30)), 'meta' => ['configured' => true, 'error' => null]]);
        } catch (GoogleAnalyticsUnavailable $exception) {
            return response()->json(['data' => null, 'meta' => ['configured' => true, 'error' => $exception->getMessage()]]);
        }
    }
}
