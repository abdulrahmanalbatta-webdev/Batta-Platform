<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use App\Support\DashboardSummary;
use Illuminate\Http\JsonResponse;

class DashboardController extends Controller
{
    /**
     * Everything the dashboard home page shows.
     */
    public function __invoke(DashboardSummary $summary): JsonResponse
    {
        return response()->json(['data' => [
            ...$summary->build(),
            'recent_orders' => OrderResource::collection(Order::query()->with('student')->latest()->latest('id')->limit(6)->get()),
        ]]);
    }
}
