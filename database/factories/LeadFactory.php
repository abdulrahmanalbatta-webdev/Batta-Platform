<?php

namespace Database\Factories;

use App\Enums\LeadService;
use App\Enums\LeadStage;
use App\Models\Lead;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Lead>
 */
class LeadFactory extends Factory
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
            'company' => fake()->company(),
            'email' => fake()->unique()->safeEmail(),
            'service' => fake()->randomElement(LeadService::cases()),
            'budget' => fake()->numberBetween(3, 60) * 100,
            'stage' => LeadStage::New,
            'note' => fake()->sentence(),
        ];
    }

    public function stage(LeadStage $stage): static
    {
        return $this->state(fn (array $attributes) => ['stage' => $stage]);
    }
}
