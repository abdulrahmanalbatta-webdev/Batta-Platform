<?php

namespace App\Http\Controllers\Site;

use App\Enums\ReviewStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\Site\OwnReviewResource;
use App\Models\Course;
use App\Models\Review;
use App\Models\Student;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MyReviewController extends Controller
{
    /**
     * Writes or rewrites the student's review of a course they can study. Every version waits for the team's
     * moderation again before it shows on the course page.
     */
    public function update(Request $request, string $slug): JsonResponse
    {
        /** @var Student $student */
        $student = $request->user();

        $course = Course::query()->where('slug', $slug)->firstOrFail();
        abort_unless($student->canAccess($course), 403, 'يمكنك تقييم الدورات المتاحة في حسابك فقط.');

        $validated = $request->validate([
            'rating' => ['required', 'integer', 'between:1,5'],
            'body' => ['required', 'string', 'min:10', 'max:2000'],
        ]);

        $review = Review::query()->firstOrNew(['student_id' => $student->id, 'course_id' => $course->id]);
        $created = ! $review->exists;
        $review->fill([...$validated, 'status' => ReviewStatus::Pending])->save();

        return (new OwnReviewResource($review))->response()->setStatusCode($created ? 201 : 200);
    }
}
