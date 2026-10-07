<?php

namespace App\Models;

use App\Enums\CourseStatus;
use App\Enums\OrderStatus;
use App\Models\Concerns\LogsActivity;
use App\Notifications\StudentPasswordReset;
use Database\Factories\StudentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;
use Laravel\Sanctum\HasApiTokens;

/**
 * A learner on the public site. Signs in there through the site API with a Sanctum token (routes/site-api.php).
 */
#[Fillable(['name', 'email', 'phone', 'country'])]
#[Hidden(['password', 'remember_token'])]
class Student extends Authenticatable
{
    /** @use HasFactory<StudentFactory> */
    use HasApiTokens, HasFactory, LogsActivity, Notifiable;

    /**
     * A student who hasn't been active for this many days counts as inactive.
     */
    public const INACTIVE_AFTER_DAYS = 30;

    /**
     * One paid "month" of Pro, in days: a fixed length, so a refund takes back exactly what the order gave.
     */
    public const PRO_PERIOD_DAYS = 30;

    /**
     * A phone with its country code, digits and spaces (e.g. +970 59 000 0000).
     */
    public const PHONE_PATTERN = '/^\+?[0-9 ]{7,20}$/';

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
            'password' => 'hashed',
        ];
    }

    protected static function booted(): void
    {
        // a suspended or deleted student is signed out of the site everywhere
        static::updated(function (Student $student): void {
            if ($student->wasChanged('suspended_at') && $student->isSuspended()) {
                $student->tokens()->delete();
            }
        });

        static::deleting(fn (Student $student) => $student->tokens()->delete());
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
     * A wa.me link to message the student on WhatsApp (digits only), when they gave a phone.
     */
    public function whatsappUrl(): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $this->phone);

        return $digits === '' ? null : 'https://wa.me/'.$digits;
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
     * Bought (enrolled), or a published course included in Pro while the membership runs.
     */
    public function canAccess(Course $course): bool
    {
        if ($this->enrollments()->where('course_id', $course->id)->exists()) {
            return true;
        }

        return $this->isPro() && $course->is_included_in_pro && $course->status === CourseStatus::Published;
    }

    /**
     * The reset link points at the public site's page, not the dashboard's.
     *
     * @param  string  $token
     */
    public function sendPasswordResetNotification($token): void
    {
        $this->notify(new StudentPasswordReset($token));
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
        return ['pro_until', 'password', 'remember_token'];
    }
}
