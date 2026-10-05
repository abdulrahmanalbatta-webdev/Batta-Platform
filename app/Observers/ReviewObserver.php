<?php

namespace App\Observers;

use App\Enums\ReviewStatus;
use App\Models\Review;
use App\Notifications\Alerts\ReviewSubmitted;
use App\Support\TeamAlerts;
use Illuminate\Contracts\Events\ShouldHandleEventsAfterCommit;

class ReviewObserver implements ShouldHandleEventsAfterCommit
{
    public function created(Review $review): void
    {
        if ($review->status === ReviewStatus::Pending) {
            TeamAlerts::send(new ReviewSubmitted($review));
        }
    }
}
