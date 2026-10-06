<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\CommentResource;
use App\Models\Comment;
use Illuminate\Http\Request;

class CommentReplyController extends Controller
{
    /**
     * Write or edit the public reply under a comment.
     */
    public function update(Request $request, Comment $comment): CommentResource
    {
        $validated = $request->validate(['reply' => ['required', 'string', 'max:2000']], [], ['reply' => 'الرد']);

        $comment->forceFill([
            'reply' => $validated['reply'],
            'replied_by' => $request->user()->id,
            'replied_at' => now(),
        ])->save();

        return new CommentResource($comment->load(CommentController::WITH));
    }

    /**
     * Remove the reply.
     */
    public function destroy(Comment $comment): CommentResource
    {
        $comment->forceFill(['reply' => null, 'replied_by' => null, 'replied_at' => null])->save();

        return new CommentResource($comment->load(CommentController::WITH));
    }
}
