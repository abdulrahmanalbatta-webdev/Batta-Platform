<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\CourseResource;
use App\Models\Course;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class CourseCoverController extends Controller
{
    /**
     * Replace the course's cover image.
     */
    public function store(Request $request, Course $course): CourseResource
    {
        $request->validate([
            'cover' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ]);

        $previous = $course->cover_path;
        $course->forceFill(['cover_path' => $request->file('cover')->store('courses', 'public')])->save();

        if ($previous) {
            Storage::disk('public')->delete($previous);
        }

        return (new CourseResource($course))->withContent();
    }
}
