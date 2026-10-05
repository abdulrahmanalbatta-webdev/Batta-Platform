<?php

namespace App\Models;

use App\Enums\ArticleCategory;
use App\Enums\ArticleStatus;
use App\Models\Concerns\LogsActivity;
use App\Observers\ArticleObserver;
use Database\Factories\ArticleFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

#[ObservedBy(ArticleObserver::class)]
#[Fillable(['title', 'slug', 'excerpt', 'body', 'category', 'status', 'publish_at', 'is_featured', 'send_newsletter', 'meta_title', 'meta_description'])]
class Article extends Model
{
    /** @use HasFactory<ArticleFactory> */
    use HasFactory, LogsActivity;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'category' => ArticleCategory::class,
            'status' => ArticleStatus::class,
            'publish_at' => 'datetime',
            'published_at' => 'datetime',
            'is_featured' => 'boolean',
            'send_newsletter' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        // the first time an article goes live, remember when
        static::saving(function (Article $article): void {
            if ($article->status === ArticleStatus::Published && $article->published_at === null) {
                $article->published_at = now();
            }
        });
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    /**
     * @return HasMany<ArticleComment, $this>
     */
    public function comments(): HasMany
    {
        return $this->hasMany(ArticleComment::class);
    }

    /**
     * Scheduled articles whose publish time has come.
     *
     * @param  Builder<Article>  $query
     */
    public function scopeDueForPublishing(Builder $query): void
    {
        $query->where('status', ArticleStatus::Scheduled)->where('publish_at', '<=', now());
    }

    public function wordCount(): int
    {
        return count(preg_split('/\s+/u', trim((string) $this->body), -1, PREG_SPLIT_NO_EMPTY));
    }

    /**
     * Reading time at 200 words a minute, at least one minute.
     */
    public function readingMinutes(): int
    {
        return max(1, (int) round($this->wordCount() / 200));
    }

    /**
     * @return Attribute<string|null, never>
     */
    protected function coverUrl(): Attribute
    {
        return Attribute::get(fn (): ?string => $this->cover_path ? Storage::disk('public')->url($this->cover_path) : null);
    }

    public function activityLabel(): string
    {
        return 'المقال';
    }

    protected function activityStateAttribute(): ?string
    {
        return 'status';
    }

    protected function activityStateLabel(): ?string
    {
        return $this->status->label();
    }

    /**
     * @return list<string>
     */
    protected function activityIgnoredAttributes(): array
    {
        return ['published_at'];
    }

    /**
     * What the public site may show.
     *
     * @param  Builder<Article>  $query
     */
    public function scopePublished(Builder $query): void
    {
        $query->where('status', ArticleStatus::Published);
    }
}
