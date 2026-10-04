<?php

namespace App\Models;

use App\Enums\CourseCategory;
use App\Enums\CourseLevel;
use App\Enums\CourseStatus;
use App\Enums\OrderItemType;
use App\Enums\OrderStatus;
use App\Models\Concerns\LogsActivity;
use Database\Factories\CourseFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Support\Facades\Storage;

#[Fillable([
    'title', 'slug', 'short_description', 'description', 'outcomes', 'tags', 'level', 'category', 'status', 'publish_at',
    'price', 'old_price', 'has_regional_pricing', 'is_included_in_pro', 'has_certificate', 'allows_questions',
])]
class Course extends Model
{
    /** @use HasFactory<CourseFactory> */
    use HasFactory, LogsActivity;

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
            'price' => 'decimal:2',
            'old_price' => 'decimal:2',
            'has_regional_pricing' => 'boolean',
            'is_included_in_pro' => 'boolean',
            'has_certificate' => 'boolean',
            'allows_questions' => 'boolean',
        ];
    }

    /**
     * @return HasMany<CourseModule, $this>
     */
    public function modules(): HasMany
    {
        return $this->hasMany(CourseModule::class)->orderBy('position');
    }

    /**
     * @return HasManyThrough<Lesson, CourseModule, $this>
     */
    public function lessons(): HasManyThrough
    {
        return $this->hasManyThrough(Lesson::class, CourseModule::class);
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
     * Paid orders for this course (refunded and failed ones excluded).
     *
     * @return HasMany<Order, $this>
     */
    public function sales(): HasMany
    {
        return $this->hasMany(Order::class, 'item_id')
            ->where('item_type', OrderItemType::Course)
            ->where('status', OrderStatus::Completed);
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
}
