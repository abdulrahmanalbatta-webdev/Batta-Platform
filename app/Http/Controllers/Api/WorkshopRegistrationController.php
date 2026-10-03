<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Workshop;
use Illuminate\Http\JsonResponse;

class WorkshopRegistrationController extends Controller
{
    /**
     * Who has a seat: the students behind the workshop's paid orders.
     */
    public function index(Workshop $workshop): JsonResponse
    {
        return response()->json([
            'data' => $workshop->registrations()->with('student')->oldest('paid_at')->get()
                ->map(fn (Order $order): array => [
                    'student_id' => $order->student->id,
                    'name' => $order->student->name,
                    'initial' => $order->student->initial,
                    'email' => $order->student->email,
                    'order_number' => $order->number(),
                    'paid_at' => $order->paid_at?->toDateString(),
                ]),
        ]);
    }
}
