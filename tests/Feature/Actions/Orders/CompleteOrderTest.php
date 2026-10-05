<?php

namespace Tests\Feature\Actions\Orders;

use App\Actions\Orders\CompleteOrder;
use App\Actions\Orders\PlaceOrder;
use App\Enums\OrderItemType;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Models\Course;
use App\Models\Student;
use App\Models\Workshop;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class CompleteOrderTest extends TestCase
{
    use RefreshDatabase;

    public function test_paid_course_order_enrols_the_student(): void
    {
        $student = Student::factory()->create();
        $course = Course::factory()->published()->create();
        $order = app(PlaceOrder::class)->handle($student, OrderItemType::Course, $course, PaymentMethod::Card);

        $order = app(CompleteOrder::class)->handle($order, 2.5);

        $this->assertSame(OrderStatus::Completed, $order->status);
        $this->assertNotNull($order->paid_at);
        $this->assertSame('2.50', $order->fee);
        $this->assertTrue($student->courses()->whereKey($course->id)->exists());
    }

    public function test_paid_workshop_order_takes_a_seat(): void
    {
        $workshop = Workshop::factory()->create(['seats' => 10]);
        $order = app(PlaceOrder::class)->handle(Student::factory()->create(), OrderItemType::Workshop, $workshop, PaymentMethod::PayPal);

        app(CompleteOrder::class)->handle($order);

        $this->assertSame(1, $workshop->seatsTaken());
    }

    public function test_pro_month_extends_an_active_membership(): void
    {
        $this->freezeSecond();
        $student = Student::factory()->create(['pro_until' => now()->addDays(10)]);
        $order = app(PlaceOrder::class)->handle($student, OrderItemType::ProMonth, null, PaymentMethod::Card);

        app(CompleteOrder::class)->handle($order);

        $this->assertEquals(now()->addDays(10 + Student::PRO_PERIOD_DAYS), $student->fresh()->pro_until);
    }

    public function test_the_last_seat_cannot_be_sold_twice(): void
    {
        $workshop = Workshop::factory()->create(['seats' => 1]);
        [$first, $second] = Student::factory()->count(2)->create()
            ->map(fn (Student $student) => app(PlaceOrder::class)->handle($student, OrderItemType::Workshop, $workshop, PaymentMethod::BankTransfer));

        app(CompleteOrder::class)->handle($first);

        $this->expectException(ValidationException::class);
        try {
            app(CompleteOrder::class)->handle($second);
        } finally {
            $this->assertSame(1, $workshop->seatsTaken());
            $this->assertSame(OrderStatus::Pending, $second->fresh()->status);
        }
    }

    public function test_a_second_order_for_an_owned_course_is_refused(): void
    {
        $student = Student::factory()->create();
        $course = Course::factory()->published()->create();
        [$first, $second] = [
            app(PlaceOrder::class)->handle($student, OrderItemType::Course, $course, PaymentMethod::BankTransfer),
            app(PlaceOrder::class)->handle($student, OrderItemType::Course, $course, PaymentMethod::BankTransfer),
        ];
        app(CompleteOrder::class)->handle($first);

        $this->expectException(ValidationException::class);
        app(CompleteOrder::class)->handle($second);
    }

    public function test_only_pending_orders_can_be_completed(): void
    {
        $order = app(PlaceOrder::class)->handle(Student::factory()->create(), OrderItemType::ProMonth, null, PaymentMethod::Card);
        app(CompleteOrder::class)->handle($order);

        $this->expectException(ValidationException::class);

        app(CompleteOrder::class)->handle($order);
    }
}
