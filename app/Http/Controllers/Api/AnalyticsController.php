<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Support\SalesReport;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AnalyticsController extends Controller
{
    /**
     * Sales and students for the last 7, 30 or 90 days, or the last 12 months (?days=365).
     */
    public function __invoke(Request $request, SalesReport $report): JsonResponse
    {
        $validated = $request->validate(['days' => ['sometimes', 'integer', Rule::in(SalesReport::PERIODS)]]);

        return response()->json(['data' => $report->build((int) ($validated['days'] ?? 30))]);
    }
}
