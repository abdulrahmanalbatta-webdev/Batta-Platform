<?php

namespace App\Http\Resources\Site;

use App\Models\LearningPath;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Collection;

/**
 * A published path: the card on the paths page (with its stage titles for the small map), and the whole map on the
 * path's own page, where each stage also carries the courses, workshops and articles linked to it.
 *
 * @mixin LearningPath
 */
class LearningPathResource extends JsonResource
{
    /**
     * The linked content the site may show, as [type => [id => model]]; set for the path page only.
     *
     * @var array<string, Collection<int, mixed>>|null
     */
    public ?array $linked = null;

    /**
     * @param  array<string, Collection<int, mixed>>  $linked
     */
    public function withLinked(array $linked): static
    {
        $this->linked = $linked;

        return $this;
    }

    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $stages = $this->stages ?? [];

        return [
            'id' => $this->slug,
            'title' => $this->title,
            'icon' => $this->icon,
            'summary' => (string) $this->summary,
            'audience' => (string) $this->audience,
            'duration' => (string) $this->duration,
            'outcomes' => $this->outcomes ?? [],
            'resources_count' => $this->resourcesCount(),
            'stages' => array_map(fn (array $stage): array => [
                'title' => $stage['title'],
                ...($this->linked === null ? [] : [
                    'text' => (string) ($stage['text'] ?? ''),
                    'topics' => $stage['topics'] ?? [],
                    'resources' => $stage['resources'] ?? [],
                    'courses' => CourseResource::collection($this->linkedOf($stage, 'course')),
                    'workshops' => WorkshopResource::collection($this->linkedOf($stage, 'workshop')),
                    'articles' => ArticleResource::collection($this->linkedOf($stage, 'article')),
                ]),
            ], $stages),
        ];
    }

    /**
     * The stage's linked items of one type, in the order they were added, leaving out any the site doesn't show
     * (a hidden course, a draft article, a workshop that has passed).
     *
     * @param  array<string, mixed>  $stage
     * @return list<mixed>
     */
    private function linkedOf(array $stage, string $type): array
    {
        return collect($stage['items'] ?? [])
            ->where('type', $type)
            ->map(fn (array $item): mixed => $this->linked[$type]->get($item['id']))
            ->filter()
            ->values()
            ->all();
    }
}
