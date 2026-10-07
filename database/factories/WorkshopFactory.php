<?php

namespace Database\Factories;

use App\Enums\WorkshopFormat;
use App\Models\Workshop;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Workshop>
 */
class WorkshopFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'title' => fake()->sentence(4),
            'description' => fake()->paragraph(),
            'date' => now()->addDays(fake()->numberBetween(3, 60))->toDateString(),
            'start_time' => '19:00',
            'format' => WorkshopFormat::Online,
            'place' => 'Zoom',
            'seats' => 40,
        ];
    }

    public function past(): static
    {
        return $this->state(fn (array $attributes) => ['date' => now()->subDays(10)->toDateString()]);
    }
}
