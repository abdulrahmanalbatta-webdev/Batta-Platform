<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Http\Resources\Site\CourseResource;
use App\Http\Resources\Site\OwnReviewResource;
use App\Models\Course;
use App\Models\Student;
use App\Notifications\Alerts\RegistrationReceived;
use App\Support\TeamAlerts;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class MyCourseController extends Controller
{
    /**
     * The courses the student registered in (even if since unpublished), the most recent first.
     */
    public function index(Request $request): JsonResponse
    {
        /** @var Student $student */
        $student = $request->user();

        $enrolledIds = $student->enrollments()->latest()->latest('id')->pluck('course_id');

        $courses = Course::query()->withCardNumbers()
            ->whereIn('id', $enrolledIds)
            ->get()
            ->sortBy(fn (Course $course): int => $enrolledIds->search($course->id))
            ->values();

        return response()->json(['data' => CourseResource::collection($courses)->resolve($request)]);
    }

    /**
     * One course the student registered in, with their review.
     */
    public function show(Request $request, string $slug): JsonResponse
    {
        /** @var Student $student */
        $student = $request->user();

        $course = Course::query()->where('slug', $slug)->firstOrFail();
        abort_unless($student->canAccess($course), 403, 'هذه الدورة غير متاحة في حسابك.');

        $review = $student->reviews()->where('course_id', $course->id)->first();

        return response()->json(['data' => [
            ...(new CourseResource($course))->withContent()->resolve($request),
            'my_review' => $review ? (new OwnReviewResource($review))->resolve($request) : null,
        ]]);
    }

    /**
     * Register the student in a published course (free; registering twice changes nothing).
     */
    public function store(Request $request, string $slug): JsonResponse
    {
        /** @var Student $student */
        $student = $request->user();

        $course = Course::query()->published()->where('slug', $slug)->firstOrFail();
        $enrollment = $student->enrollments()->firstOrCreate(['course_id' => $course->id]);

        if ($enrollment->wasRecentlyCreated) {
            TeamAlerts::send(new RegistrationReceived($student, $course));
        }

        return response()->json([
            'message' => 'تم تسجيلك في الدورة.',
            'data' => (new CourseResource($course->loadCount('enrollments')))->resolve($request),
        ], $enrollment->wasRecentlyCreated ? 201 : 200);
    }

    /**
     * Cancel the student's registration in a course.
     */
    public function destroy(Request $request, string $slug): Response
    {
        /** @var Student $student */
        $student = $request->user();

        $course = Course::query()->where('slug', $slug)->firstOrFail();
        $student->enrollments()->where('course_id', $course->id)->delete();

        return response()->noContent();
    }
}
