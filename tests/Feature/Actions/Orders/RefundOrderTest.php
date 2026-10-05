<?php

namespace Tests\Feature\Actions\Orders;

use App\Actions\Orders\CompleteOrder;
use App\Actions\Orders\PlaceOrder;
use App\Actions\Orders\RefundOrder;
use App\Enums\OrderItemType;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Models\Coupon;
use App\Models\Course;
use App\Models\Order;
use App\Models\Student;
use App\Models\Workshop;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class RefundOrderTest extends TestCase
{
    use RefreshDatabase;

    private function paidOrder(Student $student, OrderItemType $type, Course|Workshop|null $item, ?string $coupon = null): Order
    {
        $order = app(PlaceOrder::class)->handle($student, $type, $item, PaymentMethod::Card, $coupon);

        return app(CompleteOrder::class)->handle($order);
    }

    public function test_refund_removes_course_access_but_keeps_coupon_use(): void
    {
        $student = Student::factory()->create();
        $course = Course::factory()->published()->create();
        $coupon = Coupon::factory()->create(['code' => 'KEEP']);
        $order = $this->paidOrder($student, OrderItemType::Course, $course, 'KEEP');

        $order = app(RefundOrder::class)->handle($order);

        $this->assertSame(OrderStatus::Refunded, $order->status);
        $this->assertNotNull($order->refunded_at);
        $this->assertFalse($student->courses()->whereKey($course->id)->exists());
        $this->assertSame(1, $coupon->fresh()->times_used);
    }

    public function test_refund_frees_the_workshop_seat(): void
    {
        $workshop = Workshop::factory()->create();
        $order = $this->paidOrder(Student::factory()->create(), OrderItemType::Workshop, $workshop);

        app(RefundOrder::class)->handle($order);

        $this->assertSame(0, $workshop->seatsTaken());
    }

    public function test_refunding_the_only_pro_month_ends_membership(): void
    {
        $student = Student::factory()->create();
        $order = $this->paidOrder($student, OrderItemType::ProMonth, null);

        app(RefundOrder::class)->handle($order);

        $this->assertFalse($student->fresh()->isPro());
    }

    public function test_refunding_a_renewal_takes_back_exactly_its_period(): void
    {
        $this->travelTo(now()->setDate(2027, 1, 31)->setTime(12, 0));
        $student = Student::factory()->create(['pro_until' => now()->addDays(5)]);
        $before = $student->pro_until->copy();
        $order = app(PlaceOrder::class)->handle($student, OrderItemType::ProMonth, null, PaymentMethod::Card);
        app(CompleteOrder::class)->handle($order);

        app(RefundOrder::class)->handle($order);

        $this->assertEquals($before, $student->fresh()->pro_until);
    }

    public function test_pending_order_cannot_be_refunded(): void
    {
        $order = app(PlaceOrder::class)->handle(Student::factory()->create(), OrderItemType::ProMonth, null, PaymentMethod::Card);

        $this->expectException(ValidationException::class);

        app(RefundOrder::class)->handle($order);
    }
}
