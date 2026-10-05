<?php

namespace Database\Factories;

use App\Enums\ReviewStatus;
use App\Models\Course;
use App\Models\Review;
use App\Models\Student;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Review>
 */
class ReviewFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'student_id' => Student::factory(),
            'course_id' => Course::factory(),
            'rating' => fake()->numberBetween(1, 5),
            'body' => fake()->paragraph(),
            'status' => ReviewStatus::Pending,
        ];
    }

    public function published(): static
    {
        return $this->state(fn (array $attributes) => ['status' => ReviewStatus::Published]);
    }

    public function hidden(): static
    {
        return $this->state(fn (array $attributes) => ['status' => ReviewStatus::Hidden]);
    }
}
