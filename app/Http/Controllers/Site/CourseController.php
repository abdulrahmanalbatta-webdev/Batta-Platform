<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Http\Resources\Site\CourseResource;
use App\Models\Course;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class CourseController extends Controller
{
    /**
     * The published catalogue, newest first.
     */
    public function index(): AnonymousResourceCollection
    {
        return CourseResource::collection(Course::query()->withCardNumbers()->published()->latest()->latest('id')->get());
    }

    /**
     * A published course's page with its curriculum (lesson titles and lengths; the lessons themselves stay
     * behind the student's access).
     */
    public function show(string $slug): CourseResource
    {
        return (new CourseResource(Course::query()->withCardNumbers()->published()->where('slug', $slug)->firstOrFail()))->withContent();
    }
}
