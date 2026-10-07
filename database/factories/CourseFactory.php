<?php

namespace Database\Factories;

use App\Enums\CourseCategory;
use App\Enums\CourseLevel;
use App\Enums\CourseStatus;
use App\Models\Course;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Course>
 */
class CourseFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $title = fake()->sentence(4);

        return [
            'title' => $title,
            'slug' => Str::slug($title).'-'.Str::lower(Str::random(4)),
            'short_description' => fake()->sentence(8),
            'description' => fake()->paragraph(),
            'outcomes' => [fake()->sentence(3)],
            'tags' => [fake()->word()],
            'level' => CourseLevel::Intermediate,
            'category' => CourseCategory::Frontend,
            'status' => CourseStatus::Draft,
        ];
    }

    public function published(): static
    {
        return $this->state(fn (array $attributes) => ['status' => CourseStatus::Published]);
    }
}
