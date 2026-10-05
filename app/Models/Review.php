<?php

namespace App\Models;

use App\Enums\ReviewStatus;
use App\Models\Concerns\LogsActivity;
use App\Observers\ReviewObserver;
use Database\Factories\ReviewFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[ObservedBy(ReviewObserver::class)]
#[Fillable(['student_id', 'course_id', 'rating', 'body', 'status'])]
class Review extends Model
{
    /** @use HasFactory<ReviewFactory> */
    use HasFactory, LogsActivity;

    /**
     * Mirrors the column default: a new review waits for moderation.
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
            'rating' => 'integer',
            'replied_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Student, $this>
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    /**
     * @return BelongsTo<Course, $this>
     */
    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
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
        return 'تقييم';
    }

    public function activityName(): string
    {
        return $this->student->name.' على '.$this->course->title;
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
