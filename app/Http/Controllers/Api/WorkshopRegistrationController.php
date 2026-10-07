<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Workshop;
use App\Models\WorkshopRegistration;
use Illuminate\Http\JsonResponse;

class WorkshopRegistrationController extends Controller
{
    /**
     * Who has a seat, in the order they registered.
     */
    public function index(Workshop $workshop): JsonResponse
    {
        return response()->json([
            'data' => $workshop->registrations()->with('student')->oldest()->oldest('id')->get()
                ->map(fn (WorkshopRegistration $registration): array => [
                    'student_id' => $registration->student->id,
                    'name' => $registration->student->name,
                    'initial' => $registration->student->initial,
                    'email' => $registration->student->email,
                    'registered_at' => $registration->created_at->toDateString(),
                ]),
        ]);
    }
}
