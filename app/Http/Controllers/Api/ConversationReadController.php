<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ConversationResource;
use App\Models\Conversation;

class ConversationReadController extends Controller
{
    /**
     * Mark a conversation as read (the page does this when a member opens it).
     */
    public function store(Conversation $conversation): ConversationResource
    {
        $conversation->update(['read_at' => now()]);

        return new ConversationResource($conversation->load(['lead:id,company', 'latestMessage']));
    }

    /**
     * Mark a conversation as unread again, to come back to it later.
     */
    public function destroy(Conversation $conversation): ConversationResource
    {
        $conversation->update(['read_at' => null]);

        return new ConversationResource($conversation->load(['lead:id,company', 'latestMessage']));
    }
}
