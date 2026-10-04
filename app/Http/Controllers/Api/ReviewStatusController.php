<?php

namespace App\Http\Controllers\Api;

use App\Enums\ReviewStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\ReviewResource;
use App\Models\Review;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ReviewStatusController extends Controller
{
    /**
     * Publish a review on the site or hide it (a hidden review no longer counts towards the course rating).
     */
    public function update(Request $request, Review $review): ReviewResource
    {
        $validated = $request->validate([
            'status' => ['required', Rule::enum(ReviewStatus::class)->only([ReviewStatus::Published, ReviewStatus::Hidden])],
        ]);

        $review->update($validated);

        return new ReviewResource($review->load(['student:id,name', 'course:id,title', 'replier:id,name']));
    }
}
