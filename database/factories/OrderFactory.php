<?php

namespace Database\Factories;

use App\Enums\OrderItemType;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Models\Course;
use App\Models\Order;
use App\Models\Student;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Raw order rows for tests of lists and filters; real orders go through PlaceOrder and CompleteOrder.
 *
 * @extends Factory<Order>
 */
class OrderFactory extends Factory
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
            'item_type' => OrderItemType::Course,
            'item_id' => Course::factory(),
            'item_name' => fake()->sentence(3),
            'subtotal' => 49,
            'discount' => 0,
            'total' => 49,
            'payment_method' => PaymentMethod::Card,
            'status' => OrderStatus::Completed,
            'paid_at' => now(),
        ];
    }

    public function pending(): static
    {
        return $this->state(fn (array $attributes) => ['status' => OrderStatus::Pending, 'paid_at' => null]);
    }
}
