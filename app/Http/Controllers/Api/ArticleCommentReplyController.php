<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ArticleCommentResource;
use App\Models\ArticleComment;
use Illuminate\Http\Request;

class ArticleCommentReplyController extends Controller
{
    /**
     * Write or edit the public reply under a comment.
     */
    public function update(Request $request, ArticleComment $comment): ArticleCommentResource
    {
        $validated = $request->validate(['reply' => ['required', 'string', 'max:2000']], [], ['reply' => 'الرد']);

        $comment->forceFill([
            'reply' => $validated['reply'],
            'replied_by' => $request->user()->id,
            'replied_at' => now(),
        ])->save();

        return new ArticleCommentResource($comment->load(['student:id,name', 'article:id,title,slug', 'replier:id,name']));
    }

    /**
     * Remove the reply.
     */
    public function destroy(ArticleComment $comment): ArticleCommentResource
    {
        $comment->forceFill(['reply' => null, 'replied_by' => null, 'replied_at' => null])->save();

        return new ArticleCommentResource($comment->load(['student:id,name', 'article:id,title,slug']));
    }
}
