<?php

namespace App\Models;

use App\Enums\CourseCategory;
use App\Enums\CourseLevel;
use App\Enums\CourseStatus;
use App\Enums\ReviewStatus;
use App\Models\Concerns\HasComments;
use App\Models\Concerns\LogsActivity;
use Database\Factories\CourseFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

#[Fillable([
    'title', 'slug', 'short_description', 'description', 'outcomes', 'tags', 'level', 'category', 'status', 'publish_at',
    'has_certificate', 'allows_questions',
])]
class Course extends Model
{
    /** @use HasFactory<CourseFactory> */
    use HasComments, HasFactory, LogsActivity;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'outcomes' => 'array',
            'tags' => 'array',
            'level' => CourseLevel::class,
            'category' => CourseCategory::class,
            'status' => CourseStatus::class,
            'publish_at' => 'date',
            'has_certificate' => 'boolean',
            'allows_questions' => 'boolean',
        ];
    }

    /**
     * @return HasMany<Enrollment, $this>
     */
    public function enrollments(): HasMany
    {
        return $this->hasMany(Enrollment::class);
    }

    /**
     * The short mark on the course thumbnail: the first Latin word of the title ("Next.js", "APIs"), else its first letter.
     */
    public function glyph(): string
    {
        return preg_match('/[A-Za-z][A-Za-z0-9.+#]*/', $this->title, $match)
            ? mb_substr($match[0], 0, 7)
            : mb_substr(trim($this->title), 0, 1);
    }

    /**
     * @return HasMany<Review, $this>
     */
    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
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
        return 'الدورة';
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
     * What the public site may show.
     *
     * @param  Builder<Course>  $query
     */
    public function scopePublished(Builder $query): void
    {
        $query->where('status', CourseStatus::Published);
    }

    /**
     * The numbers a course card shows: enrolments, and the count and average of published reviews.
     *
     * @param  Builder<Course>  $query
     */
    public function scopeWithCardNumbers(Builder $query): void
    {
        $published = fn ($reviews) => $reviews->where('status', ReviewStatus::Published);

        $query->withCount(['enrollments', 'reviews' => $published])
            ->withAvg(['reviews' => $published], 'rating');
    }
}
