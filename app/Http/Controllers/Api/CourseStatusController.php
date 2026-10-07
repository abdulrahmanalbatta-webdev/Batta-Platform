<?php

namespace App\Http\Controllers\Api;

use App\Enums\CourseStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\CourseResource;
use App\Models\Course;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CourseStatusController extends Controller
{
    /**
     * Publish, hide or send a course to review from the courses list (one row or a bulk selection).
     */
    public function update(Request $request, Course $course): CourseResource
    {
        $request->validate(['status' => ['required', Rule::enum(CourseStatus::class)]]);
        $status = $request->enum('status', CourseStatus::class);

        $course->update(['status' => $status]);

        return new CourseResource($course);
    }
}
