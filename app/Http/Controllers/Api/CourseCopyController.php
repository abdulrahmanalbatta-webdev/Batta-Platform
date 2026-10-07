<?php

namespace App\Http\Controllers\Api;

use App\Enums\CourseStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\CourseResource;
use App\Models\Course;
use App\Support\Slug;

class CourseCopyController extends Controller
{
    /**
     * Copy a course as a new draft (the cover image is not copied).
     */
    public function store(Course $course): CourseResource
    {
        $copy = $course->replicate(['slug', 'cover_path']);
        $copy->title = "{$course->title} (نسخة)";
        $copy->slug = Slug::unique(Course::class, "{$course->slug} copy", 'course');
        $copy->status = CourseStatus::Draft;
        $copy->save();

        return new CourseResource($copy);
    }
}
