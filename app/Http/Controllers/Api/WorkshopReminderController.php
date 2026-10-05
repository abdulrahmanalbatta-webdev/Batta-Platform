<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Workshop;
use App\Notifications\WorkshopReminder;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

class WorkshopReminderController extends Controller
{
    /**
     * Email every registered student a reminder (queued).
     *
     * @throws ValidationException
     */
    public function store(Workshop $workshop): JsonResponse
    {
        if ($workshop->hasEnded()) {
            throw ValidationException::withMessages(['workshop' => 'انتهت هذه الورشة.']);
        }

        $students = $workshop->registrations()->with('student')->get()->map(fn (Order $order) => $order->student)->unique('id');

        Notification::send($students, new WorkshopReminder($workshop));

        return response()->json(['sent' => $students->count()]);
    }
}
