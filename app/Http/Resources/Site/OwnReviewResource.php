<?php

namespace App\Http\Resources\Site;

use App\Models\Review;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The signed-in student's own review of a course, with where it stands in moderation.
 *
 * @mixin Review
 */
class OwnReviewResource extends JsonResource
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
            'rating' => $this->rating,
            'body' => $this->body,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'reply' => $this->reply,
            'date' => $this->updated_at->toDateString(),
        ];
    }
}
