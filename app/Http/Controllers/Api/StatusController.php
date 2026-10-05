<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

class StatusController extends Controller
{
    /**
     * Confirm the dashboard API is reachable and report the app settings the dashboard relies on.
     */
    public function __invoke(): JsonResponse
    {
        return response()->json([
            'data' => [
                'app' => config('app.name'),
                'locale' => app()->getLocale(),
                'timezone' => config('app.timezone'),
                'time' => now()->toIso8601String(),
            ],
        ]);
    }
}
