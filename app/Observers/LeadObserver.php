<?php

namespace App\Observers;

use App\Models\Lead;
use App\Notifications\Alerts\LeadReceived;
use App\Support\TeamAlerts;
use Illuminate\Contracts\Events\ShouldHandleEventsAfterCommit;

class LeadObserver implements ShouldHandleEventsAfterCommit
{
    public function created(Lead $lead): void
    {
        TeamAlerts::send(new LeadReceived($lead));
    }
}
