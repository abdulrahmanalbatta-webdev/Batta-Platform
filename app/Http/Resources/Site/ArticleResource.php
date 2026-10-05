<?php

namespace App\Http\Resources\Site;

use App\Models\Article;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A published article: the list card, plus the body and SEO fields on the article page.
 *
 * @mixin Article
 */
class ArticleResource extends JsonResource
{
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
            'excerpt' => $this->excerpt,
            'category' => $this->category->value,
            'category_label' => $this->category->label(),
            'is_featured' => $this->is_featured,
            'reading_minutes' => $this->readingMinutes(),
            'cover_url' => $this->cover_url,
            'author' => $this->author?->name,
            'published_at' => $this->published_at?->toIso8601String(),
            $this->mergeWhen($this->withContent, fn (): array => [
                'body' => $this->body,
                'meta_title' => $this->meta_title,
                'meta_description' => $this->meta_description,
                'updated_at' => $this->updated_at->toIso8601String(),
            ]),
        ];
    }
}
