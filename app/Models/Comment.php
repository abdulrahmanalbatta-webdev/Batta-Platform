<?php

namespace App\Models;

use App\Enums\ReviewStatus;
use App\Models\Concerns\LogsActivity;
use App\Observers\CommentObserver;
use Database\Factories\CommentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * A student's comment under an article, a course or a workshop, or a student's reply to one (one level deep).
 * It goes through the same moderation as course reviews (pending, published, hidden) and can carry a public
 * reply from the team.
 */
#[ObservedBy(CommentObserver::class)]
#[Fillable(['commentable_type', 'commentable_id', 'parent_id', 'student_id', 'body', 'status'])]
class Comment extends Model
{
    /** @use HasFactory<CommentFactory> */
    use HasFactory, LogsActivity;

    /**
     * What a comment can be left on, by the key the site and the dashboard use.
     *
     * @var array<string, class-string<Model>>
     */
    public const TARGETS = [
        'article' => Article::class,
        'course' => Course::class,
        'workshop' => Workshop::class,
    ];

    /**
     * Mirrors the column default: a new comment waits for moderation.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'pending',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => ReviewStatus::class,
            'replied_at' => 'datetime',
        ];
    }

    /**
     * The article, course or workshop it was left on.
     *
     * @return MorphTo<Model, $this>
     */
    public function commentable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * The comment this one answers.
     *
     * @return BelongsTo<Comment, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(Comment::class, 'parent_id');
    }

    /**
     * Students' replies to this comment.
     *
     * @return HasMany<Comment, $this>
     */
    public function replies(): HasMany
    {
        return $this->hasMany(Comment::class, 'parent_id');
    }

    /**
     * @return BelongsTo<Student, $this>
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function replier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'replied_by');
    }

    /**
     * @param  Builder<Comment>  $query
     */
    public function scopePublished(Builder $query): void
    {
        $query->where('status', ReviewStatus::Published);
    }

    /**
     * "article", "course" or "workshop".
     */
    public function targetType(): string
    {
        return array_search($this->commentable_type, self::TARGETS, true) ?: 'article';
    }

    public function activityLabel(): string
    {
        return $this->parent_id ? 'رد على تعليق' : 'تعليق';
    }

    public function activityName(): string
    {
        return $this->student->name.' على '.$this->commentable?->title;
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
        return ['replied_by', 'replied_at'];
    }
}
