<?php

namespace Database\Factories;

use App\Models\Tool;
use App\Models\ToolCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Tool>
 */
class ToolFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tool_category_id' => ToolCategory::factory(),
            'name' => fake()->unique()->company(),
            'short' => strtoupper(fake()->lexify('??')),
            'color' => '#0066ff',
            'why' => fake()->sentence(6),
            'since' => fake()->numberBetween(2015, 2026),
            'url' => fake()->url(),
            'is_affiliate' => false,
            'is_published' => true,
            'position' => fake()->numberBetween(0, 50),
        ];
    }

    public function draft(): static
    {
        return $this->state(fn (array $attributes) => ['is_published' => false]);
    }
}
