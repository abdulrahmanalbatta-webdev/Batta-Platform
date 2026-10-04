<?php

namespace App\Http\Resources;

use App\Models\Conversation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Conversation
 */
class ConversationResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $last = $this->latestMessage;

        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'initial' => $this->initial,
            'label' => $this->contactLabel(),
            'student_id' => $this->student_id,
            'lead_id' => $this->lead_id,
            'unread' => $this->isUnread(),
            'last_message' => $last === null ? null : [
                'body' => $last->body,
                'from_contact' => $last->from_contact,
                'attachment_name' => $last->attachment_name,
            ],
            'last_message_at' => $this->last_message_at?->toIso8601String(),
            'messages' => ConversationMessageResource::collection($this->whenLoaded('messages')),
        ];
    }
}
