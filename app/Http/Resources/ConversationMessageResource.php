<?php

namespace App\Http\Resources;

use App\Models\ConversationMessage;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ConversationMessage
 */
class ConversationMessageResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'from_contact' => $this->from_contact,
            'sender' => $this->from_contact ? null : $this->sender?->name,
            'body' => $this->body,
            'attachment' => $this->attachment_path === null ? null : [
                'name' => $this->attachment_name,
                'url' => route('api.conversations.messages.attachment', [$this->conversation_id, $this->id]),
            ],
            'at' => $this->created_at->toIso8601String(),
        ];
    }
}
