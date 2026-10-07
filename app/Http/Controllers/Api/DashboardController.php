<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Support\DashboardSummary;
use Illuminate\Http\JsonResponse;

class DashboardController extends Controller
{
    /**
     * Everything the dashboard home page shows.
     */
    public function __invoke(DashboardSummary $summary): JsonResponse
    {
        return response()->json(['data' => $summary->build()]);
    }
}
