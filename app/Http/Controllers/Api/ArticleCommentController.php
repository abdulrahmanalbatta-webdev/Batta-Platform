<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ArticleCommentResource;
use App\Models\ArticleComment;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class ArticleCommentController extends Controller
{
    /**
     * Every comment, newest first, with its student, article and reply.
     */
    public function index(): AnonymousResourceCollection
    {
        return ArticleCommentResource::collection(
            ArticleComment::query()->with(['student:id,name', 'article:id,title,slug', 'replier:id,name'])->latest()->latest('id')->get(),
        );
    }

    /**
     * Delete a comment for good (spam); hiding keeps it out of the site without losing it.
     */
    public function destroy(ArticleComment $comment): Response
    {
        $comment->delete();

        return response()->noContent();
    }
}
