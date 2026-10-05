<?php

namespace App\Http\Resources\Site;

use App\Models\Review;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Str;

/**
 * A published review on a course page. Only the student's first name is shown, never their email.
 *
 * @mixin Review
 */
class ReviewResource extends JsonResource
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
            'rating' => $this->rating,
            'body' => $this->body,
            'reply' => $this->reply,
            'replied_at' => $this->replied_at?->toIso8601String(),
            'date' => $this->created_at->toDateString(),
        ];
    }
}
