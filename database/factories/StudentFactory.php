<?php

namespace Database\Factories;

use App\Models\Student;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Student>
 */
class StudentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'phone' => '+97059'.fake()->numerify('#######'),
            'country' => fake()->randomElement(['فلسطين', 'الأردن', 'السعودية', 'مصر']),
            'last_active_at' => now()->subDays(fake()->numberBetween(0, 10)),
        ];
    }

    /**
     * A student who signed up on the site and can sign in with this password.
     */
    public function withPassword(string $password = 'Secret-pass-1'): static
    {
        return $this->state(fn (array $attributes) => ['password' => $password]);
    }

    public function pro(): static
    {
        return $this->state(fn (array $attributes) => ['pro_until' => now()->addMonth()]);
    }

    public function suspended(): static
    {
        return $this->state(fn (array $attributes) => ['suspended_at' => now()->subDay()]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => ['last_active_at' => now()->subDays(60)]);
    }
}
