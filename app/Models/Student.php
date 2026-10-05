<?php

namespace App\Models;

use App\Enums\OrderStatus;
use App\Models\Concerns\LogsActivity;
use Database\Factories\StudentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;

#[Fillable(['name', 'email', 'country'])]
class Student extends Model
{
    /** @use HasFactory<StudentFactory> */
    use HasFactory, LogsActivity, Notifiable;

    /**
     * A student who hasn't been active for this many days counts as inactive.
     */
    public const INACTIVE_AFTER_DAYS = 30;

    /**
     * One paid "month" of Pro, in days: a fixed length, so a refund takes back exactly what the order gave.
     */
    public const PRO_PERIOD_DAYS = 30;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'pro_until' => 'datetime',
            'suspended_at' => 'datetime',
            'last_active_at' => 'datetime',
        ];
    }

    /**
     * @return HasMany<Order, $this>
     */
    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    /**
     * @return HasMany<Enrollment, $this>
     */
    public function enrollments(): HasMany
    {
        return $this->hasMany(Enrollment::class);
    }

    /**
     * @return BelongsToMany<Course, $this>
     */
    public function courses(): BelongsToMany
    {
        return $this->belongsToMany(Course::class, 'enrollments')->withTimestamps();
    }

    /**
     * @return HasMany<Review, $this>
     */
    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }

    /**
     * @return HasMany<LessonCompletion, $this>
     */
    public function completions(): HasMany
    {
        return $this->hasMany(LessonCompletion::class);
    }

    public function isPro(): bool
    {
        return $this->pro_until !== null && $this->pro_until->isFuture();
    }

    public function isSuspended(): bool
    {
        return $this->suspended_at !== null;
    }

    /**
     * suspended | inactive | active
     */
    public function state(): string
    {
        return match (true) {
            $this->isSuspended() => 'suspended',
            $this->last_active_at === null || $this->last_active_at->lt(now()->subDays(self::INACTIVE_AFTER_DAYS)) => 'inactive',
            default => 'active',
        };
    }

    /**
     * What the student has paid for, net of refunds (uses withSum when loaded).
     */
    public function totalSpent(): float
    {
        return (float) ($this->orders_sum_total ?? $this->orders()->where('status', OrderStatus::Completed)->sum('total'));
    }

    /**
     * @return Attribute<string, never>
     */
    protected function initial(): Attribute
    {
        return Attribute::get(fn (): string => Str::substr(trim($this->name), 0, 1));
    }

    public function activityLabel(): string
    {
        return 'الطالب';
    }

    protected function activityStateAttribute(): ?string
    {
        return 'suspended_at';
    }

    protected function activityStateLabel(): ?string
    {
        return $this->isSuspended() ? 'موقوف' : 'نشط';
    }

    /**
     * @return list<string>
     */
    protected function activityIgnoredAttributes(): array
    {
        return ['pro_until'];
    }
}
