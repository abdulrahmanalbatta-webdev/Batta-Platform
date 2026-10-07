<?php

namespace App\Http\Controllers\Api;

use App\Enums\ReviewStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\CourseRequest;
use App\Http\Resources\CourseResource;
use App\Models\Course;
use App\Support\Slug;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class CourseController extends Controller
{
    /**
     * Every course, newest first, with its enrolments and rating.
     */
    public function index(): AnonymousResourceCollection
    {
        return CourseResource::collection(
            Course::query()
                ->withCount('enrollments')
                ->withAvg(['reviews' => fn ($query) => $query->where('status', ReviewStatus::Published)], 'rating')
                ->latest()->latest('id')
                ->get(),
        );
    }

    public function show(Course $course): CourseResource
    {
        return (new CourseResource($course))->withContent();
    }

    /**
     * Create a course; without a slug one is made from the title.
     */
    public function store(CourseRequest $request): CourseResource
    {
        $course = new Course($request->courseAttributes());
        $course->slug = $request->filled('slug') ? $request->validated('slug') : Slug::unique(Course::class, $course->title, 'course');
        $course->save();

        return (new CourseResource($course))->withContent();
    }

    /**
     * Save the course form.
     */
    public function update(CourseRequest $request, Course $course): CourseResource
    {
        $course->fill($request->courseAttributes());

        if ($request->filled('slug')) {
            $course->slug = $request->validated('slug');
        }

        $course->save();

        return (new CourseResource($course->refresh()))->withContent();
    }

    /**
     * Delete a course that nobody is enrolled in (a course with students can be hidden instead).
     *
     * @throws ValidationException
     */
    public function destroy(Course $course): Response
    {
        if ($course->enrollments()->exists()) {
            throw ValidationException::withMessages(['course' => "لا يمكن حذف \"{$course->title}\" لأن فيها طلاباً مسجلين. أخفِها بدلاً من ذلك."]);
        }

        if ($course->cover_path) {
            Storage::disk('public')->delete($course->cover_path);
        }

        $course->delete();

        return response()->noContent();
    }
}
