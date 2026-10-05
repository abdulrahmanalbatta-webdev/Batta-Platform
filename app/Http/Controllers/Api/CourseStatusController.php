<?php

namespace App\Http\Controllers\Api;

use App\Enums\CourseStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\CourseResource;
use App\Models\Course;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class CourseStatusController extends Controller
{
    /**
     * Publish, hide or send a course to review from the courses list (one row or a bulk selection).
     *
     * @throws ValidationException
     */
    public function update(Request $request, Course $course): CourseResource
    {
        $request->validate(['status' => ['required', Rule::enum(CourseStatus::class)]]);
        $status = $request->enum('status', CourseStatus::class);

        if ($status === CourseStatus::Published && ! $course->lessons()->whereNotNull('lessons.title')->where('lessons.title', '!=', '')->exists()) {
            throw ValidationException::withMessages(['status' => "لا يمكن نشر \"{$course->title}\" قبل إضافة درس واحد على الأقل."]);
        }

        $course->update(['status' => $status]);

        return new CourseResource($course->loadCount('lessons')->loadSum('lessons', 'duration_seconds'));
    }
}
