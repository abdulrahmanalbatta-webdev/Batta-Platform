<?php

namespace Database\Factories;

use App\Enums\ReviewStatus;
use App\Models\Article;
use App\Models\ArticleComment;
use App\Models\Student;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ArticleComment>
 */
class ArticleCommentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'article_id' => Article::factory(),
            'student_id' => Student::factory(),
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
