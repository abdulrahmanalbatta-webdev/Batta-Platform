<?php

namespace App\Models;

use App\Enums\CouponScope;
use App\Enums\DiscountType;
use Database\Factories\CouponFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['code', 'type', 'value', 'scope', 'course_id', 'usage_limit', 'expires_on', 'is_active'])]
class Coupon extends Model
{
    /** @use HasFactory<CouponFactory> */
    use HasFactory;

    /**
     * Mirrors the column defaults, so a just-created coupon reads as active and unused before it's reloaded.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'is_active' => true,
        'times_used' => 0,
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => DiscountType::class,
            'scope' => CouponScope::class,
            'value' => 'decimal:2',
            'expires_on' => 'date',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Course, $this>
     */
    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    /**
     * Expired after the end of its last day.
     */
    public function isExpired(): bool
    {
        return $this->expires_on->endOfDay()->isPast();
    }

    public function isExhausted(): bool
    {
        return $this->usage_limit !== null && $this->times_used >= $this->usage_limit;
    }

    /**
     * expired | suspended | active
     */
    public function state(): string
    {
        return match (true) {
            $this->isExpired() => 'expired',
            ! $this->is_active => 'suspended',
            default => 'active',
        };
    }

    /**
     * The discount on a price, never more than the price itself.
     */
    public function discountFor(float $price): float
    {
        $discount = $this->type === DiscountType::Percent
            ? round($price * (float) $this->value / 100, 2)
            : (float) $this->value;

        return min($discount, $price);
    }
}
