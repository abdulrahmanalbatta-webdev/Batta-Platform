<?php

namespace App\Http\Resources;

use App\Models\Review;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
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
            'student_id' => $this->student_id,
            'name' => $this->student->name,
            'initial' => $this->student->initial,
            'course_id' => $this->course_id,
            'course' => $this->course->title,
            'rating' => $this->rating,
            'body' => $this->body,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'reply' => $this->reply,
            'replied_by' => $this->replier?->name,
            'replied_at' => $this->replied_at?->toIso8601String(),
            'date' => $this->created_at->toDateString(),
        ];
    }
}
