<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use Database\Factories\LearningPathFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * A learning path ("مسار"): a map for one specialty, its stages in order. Each stage holds the topics it covers,
 * free resources from around the web, and the platform's own courses, workshops and articles that fit it.
 *
 * @property list<array{title: string, text: ?string, topics: list<string>, resources: list<array{title: string, url: string, type: string, lang: string}>, items: list<array{type: string, id: int}>}> $stages
 */
#[Fillable(['title', 'slug', 'icon', 'summary', 'audience', 'duration', 'outcomes', 'stages', 'is_published'])]
class LearningPath extends Model
{
    /** @use HasFactory<LearningPathFactory> */
    use HasFactory, LogsActivity;

    /**
     * What a free resource is, and the language it's in.
     */
    public const RESOURCE_TYPES = ['video' => 'فيديو', 'course' => 'دورة', 'docs' => 'توثيق', 'article' => 'مقال', 'book' => 'كتاب', 'practice' => 'تمارين'];

    public const LANGUAGES = ['ar' => 'عربي', 'en' => 'إنجليزي'];

    /**
     * The platform's own content a stage can point to.
     */
    public const ITEM_TYPES = ['course' => 'دورة', 'workshop' => 'ورشة', 'article' => 'مقال'];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'outcomes' => 'array',
            'stages' => 'array',
            'is_published' => 'boolean',
        ];
    }

    /**
     * What the public site may show.
     *
     * @param  Builder<LearningPath>  $query
     */
    public function scopePublished(Builder $query): void
    {
        $query->where('is_published', true);
    }

    /**
     * @param  Builder<LearningPath>  $query
     */
    public function scopeOrdered(Builder $query): void
    {
        $query->orderBy('position')->orderBy('id');
    }

    /**
     * The linked content of every stage, as [type => ids].
     *
     * @return array<string, list<int>>
     */
    public function itemIds(): array
    {
        return collect($this->stages ?? [])
            ->flatMap(fn (array $stage): array => $stage['items'] ?? [])
            ->groupBy('type')
            ->map(fn (Collection $items): array => $items->pluck('id')->map(fn (mixed $id): int => (int) $id)->unique()->values()->all())
            ->all();
    }

    public function resourcesCount(): int
    {
        return collect($this->stages ?? [])->sum(fn (array $stage): int => count($stage['resources'] ?? []));
    }

    public function itemsCount(): int
    {
        return collect($this->stages ?? [])->sum(fn (array $stage): int => count($stage['items'] ?? []));
    }

    public function activityLabel(): string
    {
        return 'المسار';
    }

    protected function activityStateAttribute(): ?string
    {
        return 'is_published';
    }

    protected function activityStateLabel(): ?string
    {
        return $this->is_published ? 'منشور' : 'مخفي';
    }
}
