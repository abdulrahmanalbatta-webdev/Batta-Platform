<?php

namespace App\Models;

use App\Enums\ReviewStatus;
use App\Models\Concerns\LogsActivity;
use App\Observers\ArticleCommentObserver;
use Database\Factories\ArticleCommentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A student's comment under an article. It goes through the same moderation as course reviews (pending,
 * published, hidden) and can carry a public reply from the team.
 */
#[ObservedBy(ArticleCommentObserver::class)]
#[Fillable(['article_id', 'student_id', 'body', 'status'])]
class ArticleComment extends Model
{
    /** @use HasFactory<ArticleCommentFactory> */
    use HasFactory, LogsActivity;

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
     * @return BelongsTo<Article, $this>
     */
    public function article(): BelongsTo
    {
        return $this->belongsTo(Article::class);
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

    public function activityLabel(): string
    {
        return 'تعليق';
    }

    public function activityName(): string
    {
        return $this->student->name.' على '.$this->article->title;
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
