<?php

namespace Database\Factories;

use App\Models\Lesson;
use App\Models\LessonCompletion;
use App\Models\Student;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LessonCompletion>
 */
class LessonCompletionFactory extends Factory
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
            'lesson_id' => Lesson::factory(),
            'completed_at' => now(),
        ];
    }
}
