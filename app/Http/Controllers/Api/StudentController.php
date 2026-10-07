<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\StudentResource;
use App\Models\Enrollment;
use App\Models\Student;
use App\Models\WorkshopRegistration;
use Illuminate\Http\JsonResponse;

class StudentController extends Controller
{
    /**
     * Every student, newest first, with how many courses and workshops they registered in.
     */
    public function index(): JsonResponse
    {
        $students = Student::query()
            ->withCount(['enrollments', 'workshopRegistrations'])
            ->latest()->latest('id')
            ->get();

        return response()->json([
            'data' => $students->map(fn (Student $student) => (new StudentResource($student))->resolve()),
        ]);
    }

    /**
     * One student's profile: the courses and the workshops they registered in, newest first.
     */
    public function show(Student $student): JsonResponse
    {
        $student->loadCount(['enrollments', 'workshopRegistrations']);

        return response()->json([
            'data' => [
                ...(new StudentResource($student))->resolve(),
                'enrollments' => $student->enrollments()->with('course:id,title')->latest()->latest('id')->get()
                    ->map(fn (Enrollment $enrollment): array => [
                        'course_id' => $enrollment->course_id,
                        'title' => $enrollment->course->title,
                        'date' => $enrollment->created_at->toDateString(),
                    ]),
                'workshop_registrations' => $student->workshopRegistrations()->with('workshop:id,title,date')->latest()->latest('id')->get()
                    ->map(fn (WorkshopRegistration $registration): array => [
                        'workshop_id' => $registration->workshop_id,
                        'title' => $registration->workshop->title,
                        'workshop_date' => $registration->workshop->date->toDateString(),
                        'date' => $registration->created_at->toDateString(),
                    ]),
            ],
        ]);
    }
}
