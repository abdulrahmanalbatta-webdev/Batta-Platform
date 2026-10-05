<?php

namespace App\Http\Resources;

use App\Enums\ArticleStatus;
use App\Models\Article;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Article
 */
class ArticleResource extends JsonResource
{
    /**
     * The list leaves out the body and SEO fields; the editor (show, store, update) gets everything.
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
            'code' => 'A-'.$this->id,
            'title' => $this->title,
            'slug' => $this->slug,
            'category' => $this->category->value,
            'category_label' => $this->category->label(),
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            // the date the list shows: when it went live, when it will, or when it was last edited
            'date' => (match ($this->status) {
                ArticleStatus::Published => $this->published_at,
                ArticleStatus::Scheduled => $this->publish_at,
                ArticleStatus::Draft => $this->updated_at,
            } ?? $this->updated_at)->toDateString(),
            'publish_at' => $this->publish_at?->toIso8601String(),
            'reading_minutes' => $this->readingMinutes(),
            'views' => $this->views,
            // published comments (the list counts them in one query: withCount)
            'comments' => (int) ($this->published_comments_count ?? 0),
            'cover_url' => $this->cover_url,
            'updated_at' => $this->updated_at->toIso8601String(),
            $this->mergeWhen($this->withContent, fn (): array => [
                'excerpt' => $this->excerpt,
                'body' => $this->body,
                'is_featured' => $this->is_featured,
                'send_newsletter' => $this->send_newsletter,
                'meta_title' => $this->meta_title,
                'meta_description' => $this->meta_description,
            ]),
        ];
    }
}
