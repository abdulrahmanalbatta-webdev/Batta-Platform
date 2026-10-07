<?php

namespace App\Http\Resources\Site;

use App\Models\Course;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A published course as the public site shows it: no status or internal codes.
 *
 * @mixin Course
 */
class CourseResource extends JsonResource
{
    /**
     * The course page adds the description to the catalogue card.
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
            'title' => $this->title,
            'slug' => $this->slug,
            'short_description' => $this->short_description,
            'glyph' => $this->glyph(),
            'level' => $this->level->value,
            'level_label' => $this->level->label(),
            'category' => $this->category->value,
            'category_label' => $this->category->label(),
            'has_certificate' => $this->has_certificate,
            'students' => (int) ($this->enrollments_count ?? $this->enrollments()->count()),
            'rating' => round((float) ($this->reviews_avg_rating ?? 0), 1),
            'reviews' => (int) ($this->reviews_count ?? 0),
            'cover_url' => $this->cover_url,
            'outcomes' => $this->outcomes ?? [],
            'tags' => $this->tags ?? [],
            $this->mergeWhen($this->withContent, fn (): array => [
                'description' => $this->description,
                'allows_questions' => $this->allows_questions,
            ]),
        ];
    }
}
