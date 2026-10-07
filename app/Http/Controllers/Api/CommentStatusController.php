<?php

namespace App\Http\Controllers\Api;

use App\Enums\ReviewStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\CommentResource;
use App\Models\Comment;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CommentStatusController extends Controller
{
    /**
     * Publish a comment where it was left or hide it.
     */
    public function update(Request $request, Comment $comment): CommentResource
    {
        $validated = $request->validate([
            'status' => ['required', Rule::enum(ReviewStatus::class)->only([ReviewStatus::Published, ReviewStatus::Hidden])],
        ]);

        $comment->update($validated);

        return new CommentResource($comment->load(CommentController::WITH));
    }
}
