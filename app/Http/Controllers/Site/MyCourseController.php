<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Http\Resources\Site\CourseResource;
use App\Http\Resources\Site\OwnReviewResource;
use App\Models\Course;
use App\Models\Student;
use App\Support\CourseProgress;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MyCourseController extends Controller
{
    /**
     * The courses the student can study: the ones they bought (even if since unpublished), plus the published
     * Pro courses while their membership runs. Each with its progress; the most recently bought first.
     */
    public function index(Request $request, CourseProgress $progress): JsonResponse
    {
        /** @var Student $student */
        $student = $request->user();

        $enrolledIds = $student->enrollments()->latest()->latest('id')->pluck('course_id');

        $courses = Course::query()->withCardNumbers()
            ->where(fn ($query) => $query
                ->whereIn('id', $enrolledIds)
                ->when($student->isPro(), fn ($query) => $query->orWhere(fn ($query) => $query->published()->where('is_included_in_pro', true))))
            ->get()
            ->sortBy(fn (Course $course): int => ($position = $enrolledIds->search($course->id)) === false ? PHP_INT_MAX : $position)
            ->values();

        $perCourse = $progress->forStudent($student->id, $courses->pluck('id'));

        return response()->json(['data' => $courses->map(fn (Course $course): array => [
            ...(new CourseResource($course))->withProgress($perCourse[$course->id])->resolve($request),
            'access' => $enrolledIds->contains($course->id) ? 'purchased' : 'pro',
        ])->all()]);
    }

    /**
     * One course the student can study: the curriculum with the lessons they finished, and their review.
     */
    public function show(Request $request, string $slug, CourseProgress $progress): JsonResponse
    {
        /** @var Student $student */
        $student = $request->user();

        $course = Course::query()->where('slug', $slug)->firstOrFail();
        abort_unless($student->canAccess($course), 403, 'هذه الدورة غير متاحة في حسابك.');

        $review = $student->reviews()->where('course_id', $course->id)->first();

        return response()->json(['data' => [
            ...(new CourseResource($course))->withContent()->withProgress($progress->forStudent($student->id, [$course->id])[$course->id])->resolve($request),
            'my_review' => $review ? (new OwnReviewResource($review))->resolve($request) : null,
        ]]);
    }
}
