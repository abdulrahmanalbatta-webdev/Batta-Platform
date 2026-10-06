<?php

namespace App\Http\Resources\Site;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Str;

/**
 * A comment on the site: the student's first name only, the team's reply, and (on a top-level comment) the
 * students' published replies.
 *
 * @mixin \App\Models\Comment
 */
class CommentResource extends JsonResource
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
            'name' => Str::before(trim($this->student->name), ' '),
            'initial' => $this->student->initial,
            'body' => $this->body,
            'reply' => $this->reply,
            'replied_at' => $this->replied_at?->toIso8601String(),
            'date' => $this->created_at->toDateString(),
            'replies' => self::collection($this->whenLoaded('replies')),
        ];
    }
}
