<?php

namespace App\Http\Controllers\Api;

use App\Enums\ReviewStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\ArticleCommentResource;
use App\Models\ArticleComment;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ArticleCommentStatusController extends Controller
{
    /**
     * Publish a comment under its article or hide it.
     */
    public function update(Request $request, ArticleComment $comment): ArticleCommentResource
    {
        $validated = $request->validate([
            'status' => ['required', Rule::enum(ReviewStatus::class)->only([ReviewStatus::Published, ReviewStatus::Hidden])],
        ]);

        $comment->update($validated);

        return new ArticleCommentResource($comment->load(['student:id,name', 'article:id,title,slug', 'replier:id,name']));
    }
}
