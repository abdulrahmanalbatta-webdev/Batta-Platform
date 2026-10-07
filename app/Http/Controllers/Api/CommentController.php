<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\CommentResource;
use App\Models\Comment;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class CommentController extends Controller
{
    /**
     * The relations a comment card needs in the dashboard.
     *
     * @var list<string>
     */
    public const WITH = ['student:id,name', 'commentable', 'parent.student:id,name', 'replier:id,name'];

    /**
     * Every comment on articles, courses and workshops, newest first, with its student, where it was left,
     * the comment it answers and the team's reply.
     */
    public function index(): AnonymousResourceCollection
    {
        return CommentResource::collection(Comment::query()->with(self::WITH)->latest()->latest('id')->get());
    }

    /**
     * Delete a comment for good (spam), with the students' replies under it; hiding keeps it out of the site
     * without losing it.
     */
    public function destroy(Comment $comment): Response
    {
        $comment->delete();

        return response()->noContent();
    }
}
