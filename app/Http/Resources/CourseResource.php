<?php

namespace App\Http\Resources;

use App\Enums\ReviewStatus;
use App\Models\Course;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Course
 */
class CourseResource extends JsonResource
{
    /**
     * The list leaves out descriptions; the course form (show, store, update) gets everything.
     */
    public bool $withContent = false;

    public function withContent(): static
    {
        $this->withContent = true;

        return $this;
    }

    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => 'C-'.$this->id,
            'title' => $this->title,
            'slug' => $this->slug,
            'glyph' => $this->glyph(),
            'level' => $this->level->value,
            'level_label' => $this->level->label(),
            'category' => $this->category->value,
            'category_label' => $this->category->label(),
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'price' => (float) $this->price,
            'old_price' => $this->old_price === null ? null : (float) $this->old_price,
            'students' => (int) ($this->enrollments_count ?? $this->enrollments()->count()),
            'revenue' => (float) ($this->sales_sum_total ?? $this->sales()->sum('total')),
            // average of the published reviews, 0 while there are none
            'rating' => round((float) ($this->reviews_avg_rating ?? $this->reviews()->where('status', ReviewStatus::Published)->avg('rating')), 1),
            'cover_url' => $this->cover_url,
            'updated' => $this->updated_at->toDateString(),
            $this->mergeWhen($this->withContent, fn (): array => [
                'short_description' => $this->short_description,
                'description' => $this->description,
                'outcomes' => $this->outcomes ?? [],
                'tags' => $this->tags ?? [],
                'publish_at' => $this->publish_at?->toDateString(),
                'has_regional_pricing' => $this->has_regional_pricing,
                'is_included_in_pro' => $this->is_included_in_pro,
                'has_certificate' => $this->has_certificate,
                'allows_questions' => $this->allows_questions,
            ]),
        ];
    }
}
