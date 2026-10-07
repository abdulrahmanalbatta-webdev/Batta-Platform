<?php

namespace App\Http\Controllers\Api;

use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\StudentResource;
use App\Models\Enrollment;
use App\Models\Order;
use App\Models\Student;
use Illuminate\Http\JsonResponse;

class StudentController extends Controller
{
    /**
     * Every student, newest first, with their courses and what they've paid.
     */
    public function index(): JsonResponse
    {
        $students = Student::query()
            ->withCount('enrollments')
            ->withSum(['orders' => fn ($query) => $query->where('status', OrderStatus::Completed)], 'total')
            ->latest()->latest('id')
            ->get();

        return response()->json([
            'data' => $students->map(fn (Student $student) => (new StudentResource($student))->resolve()),
        ]);
    }

    /**
     * One student's profile: the courses they're enrolled in, newest first, and their latest orders.
     */
    public function show(Student $student): JsonResponse
    {
        $student->loadCount('enrollments');

        return response()->json([
            'data' => [
                ...(new StudentResource($student))->resolve(),
                'enrollments' => $student->enrollments()->with('course:id,title')->latest()->latest('id')->get()
                    ->map(fn (Enrollment $enrollment): array => [
                        'course_id' => $enrollment->course_id,
                        'title' => $enrollment->course->title,
                        'date' => $enrollment->created_at->toDateString(),
                    ]),
                'orders' => $student->orders()->latest()->latest('id')->limit(5)->get()
                    ->map(fn (Order $order): array => [
                        'id' => $order->id,
                        'number' => $order->number(),
                        'item_name' => $order->item_name,
                        'total' => (float) $order->total,
                        'status_label' => $order->status->label(),
                        'date' => $order->created_at->toDateString(),
                    ]),
            ],
        ]);
    }
}
