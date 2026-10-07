<?php

namespace App\Observers;

use App\Enums\ReviewStatus;
use App\Models\Comment;
use App\Notifications\Alerts\CommentSubmitted;
use App\Support\TeamAlerts;
use Illuminate\Contracts\Events\ShouldHandleEventsAfterCommit;

class CommentObserver implements ShouldHandleEventsAfterCommit
{
    public function created(Comment $comment): void
    {
        if ($comment->status === ReviewStatus::Pending) {
            TeamAlerts::send(new CommentSubmitted($comment));
        }
    }
}
