<?php

namespace Database\Factories;

use App\Models\Student;
use App\Models\Workshop;
use App\Models\WorkshopRegistration;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WorkshopRegistration>
 */
class WorkshopRegistrationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'workshop_id' => Workshop::factory(),
            'student_id' => Student::factory(),
        ];
    }
}
