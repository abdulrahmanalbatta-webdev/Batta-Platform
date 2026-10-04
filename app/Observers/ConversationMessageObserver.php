<?php

namespace App\Observers;

use App\Models\ConversationMessage;
use App\Notifications\Alerts\ContactMessageReceived;
use App\Support\TeamAlerts;
use Illuminate\Contracts\Events\ShouldHandleEventsAfterCommit;

class ConversationMessageObserver implements ShouldHandleEventsAfterCommit
{
    /**
     * A message from the contact reopens the conversation as unread and alerts the members who answer.
     */
    public function created(ConversationMessage $message): void
    {
        if (! $message->from_contact) {
            return;
        }

        $message->conversation->update(['read_at' => null, 'last_message_at' => $message->created_at]);

        TeamAlerts::send(new ContactMessageReceived($message));
    }
}
