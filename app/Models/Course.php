<?php

namespace App\Models;

use App\Enums\CourseCategory;
use App\Enums\CourseLevel;
use App\Enums\CourseStatus;
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
    use HasFactory;

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
     * @return Attribute<string|null, never>
     */
    protected function coverUrl(): Attribute
    {
        return Attribute::get(fn (): ?string => $this->cover_path ? Storage::disk('public')->url($this->cover_path) : null);
    }
}
