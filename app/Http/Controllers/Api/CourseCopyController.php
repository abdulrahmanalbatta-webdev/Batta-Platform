<?php

namespace App\Http\Controllers\Api;

use App\Enums\CourseStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\CourseResource;
use App\Models\Course;
use App\Models\CourseModule;
use App\Models\Lesson;
use App\Support\Slug;
use Illuminate\Support\Facades\DB;

class CourseCopyController extends Controller
{
    /**
     * Copy a course and its curriculum as a new draft (the cover image is not copied).
     */
    public function store(Course $course): CourseResource
    {
        $copy = DB::transaction(function () use ($course): Course {
            $copy = $course->replicate(['slug', 'cover_path']);
            $copy->title = "{$course->title} (نسخة)";
            $copy->slug = Slug::unique(Course::class, "{$course->slug} copy", 'course');
            $copy->status = CourseStatus::Draft;
            $copy->save();

            $course->modules()->with('lessons')->get()->each(function (CourseModule $module) use ($copy): void {
                $newModule = $copy->modules()->create($module->only(['title', 'position']));
                $module->lessons->each(fn (Lesson $lesson) => $newModule->lessons()->create($lesson->only(['title', 'duration_seconds', 'position'])));
            });

            return $copy;
        });

        return new CourseResource($copy->loadCount('lessons')->loadSum('lessons', 'duration_seconds'));
    }
}
