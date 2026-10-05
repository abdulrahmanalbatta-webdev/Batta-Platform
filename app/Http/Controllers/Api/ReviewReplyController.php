<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ReviewResource;
use App\Models\Review;
use Illuminate\Http\Request;

class ReviewReplyController extends Controller
{
    /**
     * Write or edit the public reply under a review.
     */
    public function update(Request $request, Review $review): ReviewResource
    {
        $validated = $request->validate(['reply' => ['required', 'string', 'max:2000']], [], ['reply' => 'الرد']);

        $review->forceFill([
            'reply' => $validated['reply'],
            'replied_by' => $request->user()->id,
            'replied_at' => now(),
        ])->save();

        return new ReviewResource($review->load(['student:id,name', 'course:id,title', 'replier:id,name']));
    }

    /**
     * Remove the reply.
     */
    public function destroy(Review $review): ReviewResource
    {
        $review->forceFill(['reply' => null, 'replied_by' => null, 'replied_at' => null])->save();

        return new ReviewResource($review->load(['student:id,name', 'course:id,title']));
    }
}
