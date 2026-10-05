<?php

namespace App\Http\Controllers\Site;

use App\Enums\ReviewStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\Site\ReviewResource;
use App\Models\Course;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class CourseReviewController extends Controller
{
    /**
     * A published course's published reviews, newest first, 20 a page.
     */
    public function index(string $slug): AnonymousResourceCollection
    {
        $course = Course::query()->published()->where('slug', $slug)->firstOrFail();

        return ReviewResource::collection(
            $course->reviews()
                ->where('status', ReviewStatus::Published)
                ->with('student:id,name')
                ->latest()->latest('id')
                ->paginate(20),
        );
    }
}
