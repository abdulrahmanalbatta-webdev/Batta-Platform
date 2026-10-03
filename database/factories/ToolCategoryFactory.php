<?php

namespace Database\Factories;

use App\Models\ToolCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ToolCategory>
 */
class ToolCategoryFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->words(2, true),
            'color' => fake()->randomElement(['#0066ff', '#0b0d12', '#0e9f6e', '#7c3aed']),
            'position' => fake()->numberBetween(0, 50),
        ];
    }
}
