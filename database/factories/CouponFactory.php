<?php

namespace Database\Factories;

use App\Enums\CouponScope;
use App\Enums\DiscountType;
use App\Models\Coupon;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Coupon>
 */
class CouponFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => Str::upper(Str::random(8)),
            'type' => DiscountType::Percent,
            'value' => 20,
            'scope' => CouponScope::AllCourses,
            'usage_limit' => null,
            'expires_on' => now()->addMonth()->toDateString(),
            'is_active' => true,
        ];
    }

    public function expired(): static
    {
        return $this->state(fn (array $attributes) => ['expires_on' => now()->subDay()->toDateString()]);
    }
}
