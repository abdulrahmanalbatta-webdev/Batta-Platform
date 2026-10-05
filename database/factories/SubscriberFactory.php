<?php

namespace Database\Factories;

use App\Models\Subscriber;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Subscriber>
 */
class SubscriberFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'email' => fake()->unique()->safeEmail(),
            'source' => 'site',
        ];
    }

    public function unsubscribed(): static
    {
        return $this->state(fn (array $attributes) => ['unsubscribed_at' => now()->subDay()]);
    }
}
