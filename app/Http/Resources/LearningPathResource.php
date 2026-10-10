<?php

namespace App\Http\Resources;

use App\Models\LearningPath;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin LearningPath
 */
class LearningPathResource extends JsonResource
{
    /**
     * The list shows counts; the path editor (show, store, update) gets the whole map.
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
        $stages = $this->stages ?? [];

        return [
            'id' => $this->id,
            'title' => $this->title,
            'slug' => $this->slug,
            'icon' => $this->icon,
            'summary' => $this->summary,
            'duration' => $this->duration,
            'is_published' => $this->is_published,
            'stages_count' => count($stages),
            'resources_count' => $this->resourcesCount(),
            'items_count' => $this->itemsCount(),
            'updated' => $this->updated_at->toDateString(),
            $this->mergeWhen($this->withContent, fn (): array => [
                'audience' => $this->audience,
                'outcomes' => $this->outcomes ?? [],
                'stages' => $stages,
            ]),
        ];
    }
}
