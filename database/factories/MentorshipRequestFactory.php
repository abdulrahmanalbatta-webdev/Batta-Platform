<?php

namespace Database\Factories;

use App\Enums\MentorshipStatus;
use App\Models\MentorshipRequest;
use App\Models\Student;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MentorshipRequest>
 */
class MentorshipRequestFactory extends Factory
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
            'package_id' => 'monthly',
            'package_name' => 'إرشاد شهري',
            'price' => 150,
            'goal' => fake()->sentence(),
            'background' => fake()->sentence(),
            'links' => null,
            'availability' => 'مساءً بعد الساعة 6',
        ];
    }

    /**
     * Accepted, waiting for the transfer.
     */
    public function accepted(): static
    {
        return $this->state(fn (): array => ['status' => MentorshipStatus::Accepted, 'accepted_at' => now(), 'payment_due_at' => now()->addDay()]);
    }

    /**
     * The student sent a payment receipt.
     */
    public function withProof(): static
    {
        return $this->accepted()->state(fn (): array => ['status' => MentorshipStatus::ProofSubmitted, 'proof_path' => 'mentorship/receipt.jpg', 'proof_name' => 'receipt.jpg', 'proof_uploaded_at' => now()]);
    }

    public function paid(): static
    {
        return $this->withProof()->state(fn (): array => ['status' => MentorshipStatus::Paid, 'paid_at' => now()]);
    }
}
