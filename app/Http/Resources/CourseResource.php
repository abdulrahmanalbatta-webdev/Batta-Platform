<?php

namespace App\Http\Resources;

use App\Models\Course;
use App\Models\CourseModule;
use App\Models\Lesson;
use App\Support\Duration;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Course
 */
class CourseResource extends JsonResource
{
    /**
     * The list leaves out descriptions and the curriculum; the course form (show, store, update) gets everything.
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
            'lessons' => (int) ($this->lessons_count ?? $this->lessons()->count()),
            'hours' => round((int) ($this->lessons_sum_duration_seconds ?? $this->lessons()->sum('duration_seconds')) / 3600, 1),
            'students' => (int) ($this->enrollments_count ?? $this->enrollments()->count()),
            'revenue' => (float) ($this->sales_sum_total ?? $this->sales()->sum('total')),
            // reviews arrive in phase 4
            'rating' => 0,
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
                'modules' => $this->modules()->with('lessons')->get()->map(fn (CourseModule $module): array => [
                    'id' => $module->id,
                    'title' => $module->title,
                    'lessons' => $module->lessons->map(fn (Lesson $lesson): array => [
                        'id' => $lesson->id,
                        'title' => $lesson->title,
                        'duration' => Duration::format($lesson->duration_seconds),
                    ])->all(),
                ])->all(),
            ]),
        ];
    }

    /**
     * The short mark on the course thumbnail: the first Latin word of the title ("Next.js", "APIs"), else its first letter.
     */
    private function glyph(): string
    {
        return preg_match('/[A-Za-z][A-Za-z0-9.+#]*/', $this->title, $match)
            ? mb_substr($match[0], 0, 7)
            : mb_substr(trim($this->title), 0, 1);
    }
}
