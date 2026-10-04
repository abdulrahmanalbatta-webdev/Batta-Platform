<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ReviewResource;
use App\Models\Review;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class ReviewController extends Controller
{
    /**
     * Every review, newest first, with its student, course and reply.
     */
    public function index(): AnonymousResourceCollection
    {
        return ReviewResource::collection(
            Review::query()->with(['student:id,name', 'course:id,title', 'replier:id,name'])->latest()->latest('id')->get(),
        );
    }

    /**
     * Delete a review for good (spam); hiding keeps it out of the site without losing it.
     */
    public function destroy(Review $review): Response
    {
        $review->delete();

        return response()->noContent();
    }
}
