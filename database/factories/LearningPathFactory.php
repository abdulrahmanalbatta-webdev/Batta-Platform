<?php

namespace Database\Factories;

use App\Models\LearningPath;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LearningPath>
 */
class LearningPathFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'title' => 'مسار '.fake()->unique()->word(),
            'slug' => fake()->unique()->slug(2),
            'icon' => fake()->randomElement(['code', 'monitor', 'globe', 'bulb']),
            'summary' => fake()->sentence(),
            'audience' => 'للمبتدئين',
            'duration' => '3–6 أشهر',
            'outcomes' => ['بناء مشروع كامل'],
            'stages' => [
                [
                    'title' => 'الأساسيات',
                    'text' => fake()->sentence(),
                    'topics' => ['HTML', 'CSS'],
                    'resources' => [['title' => 'MDN', 'url' => 'https://developer.mozilla.org', 'type' => 'docs', 'lang' => 'en']],
                    'items' => [],
                ],
            ],
            'is_published' => true,
            'position' => fake()->numberBetween(0, 50),
        ];
    }

    /**
     * Saved but not shown on the site.
     */
    public function hidden(): static
    {
        return $this->state(fn (array $attributes) => ['is_published' => false]);
    }
}
