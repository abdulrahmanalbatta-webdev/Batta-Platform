<?php

namespace App\Http\Resources\Site;

use App\Models\Course;
use App\Models\CourseModule;
use App\Models\Lesson;
use App\Support\Duration;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A published course as the public site shows it: no revenue, status or internal codes.
 *
 * @mixin Course
 */
class CourseResource extends JsonResource
{
    /**
     * The course page adds the description, outcomes and curriculum to the catalogue card.
     */
    public bool $withContent = false;

    /**
     * For a signed-in student's own courses: percent done and the ids of the lessons they finished.
     *
     * @var array{percent: int, completed: list<int>}|null
     */
    public ?array $progress = null;

    public function withContent(): static
    {
        $this->withContent = true;

        return $this;
    }

    /**
     * @param  array{percent: int, completed: list<int>}  $progress
     */
    public function withProgress(array $progress): static
    {
        $this->progress = $progress;

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
            'price' => (float) $this->price,
            'old_price' => $this->old_price === null ? null : (float) $this->old_price,
            'is_included_in_pro' => $this->is_included_in_pro,
            'has_certificate' => $this->has_certificate,
            'lessons' => (int) ($this->lessons_count ?? $this->lessons()->count()),
            'hours' => round((int) ($this->lessons_sum_duration_seconds ?? $this->lessons()->sum('duration_seconds')) / 3600, 1),
            'students' => (int) ($this->enrollments_count ?? $this->enrollments()->count()),
            'rating' => round((float) ($this->reviews_avg_rating ?? 0), 1),
            'reviews' => (int) ($this->reviews_count ?? 0),
            'cover_url' => $this->cover_url,
            'outcomes' => $this->outcomes ?? [],
            'tags' => $this->tags ?? [],
            $this->mergeWhen($this->withContent, fn (): array => [
                'description' => $this->description,
                'has_regional_pricing' => $this->has_regional_pricing,
                'allows_questions' => $this->allows_questions,
                'modules' => $this->modules()->with('lessons')->get()->map(fn (CourseModule $module): array => [
                    'id' => $module->id,
                    'title' => $module->title,
                    'lessons' => $module->lessons->map(fn (Lesson $lesson): array => [
                        'id' => $lesson->id,
                        'title' => $lesson->title,
                        'duration' => Duration::format($lesson->duration_seconds),
                        'duration_seconds' => $lesson->duration_seconds,
                    ])->all(),
                ])->all(),
            ]),
            $this->mergeWhen($this->progress !== null, fn (): array => [
                'progress' => $this->progress['percent'],
                'completed_lessons' => $this->progress['completed'],
            ]),
        ];
    }
}
