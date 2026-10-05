<?php

namespace App\Http\Controllers\Api;

use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\StudentResource;
use App\Models\Course;
use App\Models\Order;
use App\Models\Student;
use App\Support\CourseProgress;
use Illuminate\Http\JsonResponse;

class StudentController extends Controller
{
    /**
     * Every student, newest first, with their courses, progress and what they've paid.
     */
    public function index(CourseProgress $progress): JsonResponse
    {
        $students = Student::query()
            ->withSum(['orders' => fn ($query) => $query->where('status', OrderStatus::Completed)], 'total')
            ->latest()->latest('id')
            ->get();

        $perStudent = $progress->forStudents($students->modelKeys());

        return response()->json([
            'data' => $students->map(fn (Student $student) => (new StudentResource($student, $perStudent[$student->id] ?? []))->resolve()),
        ]);
    }

    /**
     * One student's profile: their courses with progress and their latest orders.
     */
    public function show(Student $student, CourseProgress $progress): JsonResponse
    {
        $perCourse = $progress->forStudents([$student->id])[$student->id] ?? [];

        return response()->json([
            'data' => [
                ...(new StudentResource($student, $perCourse))->resolve(),
                'enrollments' => Course::query()->whereKey(array_keys($perCourse))->get(['id', 'title'])
                    ->map(fn (Course $course): array => ['course_id' => $course->id, 'title' => $course->title, 'progress' => $perCourse[$course->id]])
                    ->sortByDesc('progress')->values(),
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
