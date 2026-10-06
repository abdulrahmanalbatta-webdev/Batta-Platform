<?php

namespace Database\Factories;

use App\Enums\ReviewStatus;
use App\Models\Article;
use App\Models\Comment;
use App\Models\Student;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Comment>
 */
class CommentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'commentable_type' => Article::class,
            'commentable_id' => Article::factory(),
            'student_id' => Student::factory(),
            'body' => fake()->paragraph(),
            'status' => ReviewStatus::Pending,
        ];
    }

    public function published(): static
    {
        return $this->state(fn (array $attributes) => ['status' => ReviewStatus::Published]);
    }

    /**
     * A student's reply to another comment, on the same article, course or workshop.
     */
    public function replyTo(Comment $parent): static
    {
        return $this->state(fn (array $attributes) => [
            'commentable_type' => $parent->commentable_type,
            'commentable_id' => $parent->commentable_id,
            'parent_id' => $parent->id,
        ]);
    }

    public function hidden(): static
    {
        return $this->state(fn (array $attributes) => ['status' => ReviewStatus::Hidden]);
    }
}
