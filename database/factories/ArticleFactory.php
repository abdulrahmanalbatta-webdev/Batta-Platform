<?php

namespace Database\Factories;

use App\Enums\ArticleCategory;
use App\Enums\ArticleStatus;
use App\Models\Article;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Article>
 */
class ArticleFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $title = fake()->sentence(5);

        return [
            'title' => $title,
            'slug' => Str::slug($title).'-'.Str::lower(Str::random(4)),
            'excerpt' => fake()->sentence(12),
            'body' => fake()->paragraphs(4, true),
            'category' => ArticleCategory::Tutorials,
            'status' => ArticleStatus::Draft,
        ];
    }

    public function published(): static
    {
        return $this->state(fn (array $attributes) => ['status' => ArticleStatus::Published, 'published_at' => now()->subDay()]);
    }

    public function scheduled(?\DateTimeInterface $at = null): static
    {
        return $this->state(fn (array $attributes) => ['status' => ArticleStatus::Scheduled, 'publish_at' => $at ?? now()->addDay()]);
    }
}
