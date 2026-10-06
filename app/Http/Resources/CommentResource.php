<?php

namespace App\Http\Resources;

use App\Models\Article;
use App\Models\Course;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Str;

/**
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
        $target = $this->commentable;

        return [
            'id' => $this->id,
            'student_id' => $this->student_id,
            'name' => $this->student->name,
            'initial' => $this->student->initial,
            'type' => $this->targetType(),
            'type_label' => ['article' => 'مقال', 'course' => 'دورة', 'workshop' => 'ورشة'][$this->targetType()],
            'target_id' => $this->commentable_id,
            'target' => $target?->title,
            // where it shows on the site: /articles/{slug}, /courses/{slug}, /workshops/{id}
            'target_key' => $target instanceof Article || $target instanceof Course ? $target->slug : $this->commentable_id,
            'parent_id' => $this->parent_id,
            'parent' => $this->parent_id && $this->relationLoaded('parent') && $this->parent ? [
                'name' => $this->parent->student->name,
                'body' => Str::limit($this->parent->body, 120),
            ] : null,
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
