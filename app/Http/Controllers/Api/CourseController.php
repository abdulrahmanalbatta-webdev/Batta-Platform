<?php

namespace App\Http\Controllers\Api;

use App\Actions\SyncCurriculum;
use App\Http\Controllers\Controller;
use App\Http\Requests\CourseRequest;
use App\Http\Resources\CourseResource;
use App\Models\Course;
use App\Support\Slug;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class CourseController extends Controller
{
    /**
     * Every course, newest first, with lesson counts and total length.
     */
    public function index(): AnonymousResourceCollection
    {
        return CourseResource::collection(
            Course::query()->withCount('lessons')->withSum('lessons', 'duration_seconds')->latest()->latest('id')->get(),
        );
    }

    public function show(Course $course): CourseResource
    {
        return (new CourseResource($course))->withContent();
    }

    /**
     * Create a course with its curriculum; without a slug one is made from the title.
     */
    public function store(CourseRequest $request, SyncCurriculum $curriculum): CourseResource
    {
        $course = DB::transaction(function () use ($request, $curriculum): Course {
            $course = new Course($request->courseAttributes());
            $course->slug = $request->filled('slug') ? $request->validated('slug') : Slug::unique(Course::class, $course->title, 'course');
            $course->save();

            $curriculum->handle($course, $request->validated('modules', []));

            return $course;
        });

        return (new CourseResource($course))->withContent();
    }

    /**
     * Save the whole course form: details and curriculum.
     */
    public function update(CourseRequest $request, Course $course, SyncCurriculum $curriculum): CourseResource
    {
        DB::transaction(function () use ($request, $course, $curriculum): void {
            $course->fill($request->courseAttributes());

            if ($request->filled('slug')) {
                $course->slug = $request->validated('slug');
            }

            $course->save();
            $curriculum->handle($course, $request->validated('modules', []));
        });

        return (new CourseResource($course->refresh()))->withContent();
    }

    public function destroy(Course $course): Response
    {
        if ($course->cover_path) {
            Storage::disk('public')->delete($course->cover_path);
        }

        $course->delete();

        return response()->noContent();
    }
}
