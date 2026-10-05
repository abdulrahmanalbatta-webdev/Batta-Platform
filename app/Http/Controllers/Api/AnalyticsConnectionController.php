<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Support\GoogleAnalytics;
use App\Support\GoogleAnalyticsUnavailable;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

class AnalyticsConnectionController extends Controller
{
    /**
     * "Test the connection" on the settings page: one small report with the saved property and key.
     */
    public function store(GoogleAnalytics $analytics): JsonResponse
    {
        try {
            $analytics->check();
        } catch (GoogleAnalyticsUnavailable $exception) {
            throw ValidationException::withMessages(['ga_property_id' => $exception->getMessage()]);
        }

        return response()->json(['message' => 'تم الاتصال بـ Google Analytics بنجاح.']);
    }
}
