<?php

namespace Tests\Feature\Actions\Orders;

use App\Actions\Orders\PlaceOrder;
use App\Enums\CouponScope;
use App\Enums\DiscountType;
use App\Enums\OrderItemType;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Models\Coupon;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Order;
use App\Models\Student;
use App\Models\Workshop;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class PlaceOrderTest extends TestCase
{
    use RefreshDatabase;

    private function place(Student $student, OrderItemType $type, Course|Workshop|null $item, ?string $coupon = null): Order
    {
        return app(PlaceOrder::class)->handle($student, $type, $item, PaymentMethod::Card, $coupon);
    }

    /**
     * Runs the call and returns the message it failed with for the given field.
     */
    private function errorFrom(callable $call, string $field): ?string
    {
        try {
            $call();
        } catch (ValidationException $e) {
            return $e->errors()[$field][0] ?? null;
        }

        return null;
    }

    public function test_places_pending_course_order_with_snapshot_of_name_and_price(): void
    {
        $course = Course::factory()->published()->create(['title' => 'Next.js', 'price' => 79]);

        $order = $this->place(Student::factory()->create(), OrderItemType::Course, $course);

        $this->assertSame(OrderStatus::Pending, $order->status);
        $this->assertSame('Next.js', $order->item_name);
        $this->assertSame('79.00', $order->total);
    }

    public function test_pro_month_uses_configured_price(): void
    {
        config(['sales.pro_month_price' => 12]);

        $order = $this->place(Student::factory()->create(), OrderItemType::ProMonth, null);

        $this->assertSame('12.00', $order->total);
        $this->assertSame('اشتراك Pro شهري', $order->item_name);
    }

    public function test_percent_coupon_discounts_and_is_reserved(): void
    {
        $course = Course::factory()->published()->create(['price' => 80]);
        $coupon = Coupon::factory()->create(['code' => 'LAUNCH25', 'value' => 25]);

        $order = $this->place(Student::factory()->create(), OrderItemType::Course, $course, ' launch25 ');

        $this->assertSame('20.00', $order->discount);
        $this->assertSame('60.00', $order->total);
        $this->assertSame('LAUNCH25', $order->coupon_code);
        $this->assertSame(1, $coupon->fresh()->times_used);
    }

    public function test_fixed_coupon_never_discounts_below_zero(): void
    {
        $course = Course::factory()->published()->create(['price' => 5]);
        Coupon::factory()->create(['code' => 'TEN', 'type' => DiscountType::Fixed, 'value' => 10]);

        $order = $this->place(Student::factory()->create(), OrderItemType::Course, $course, 'TEN');

        $this->assertSame('0.00', $order->total);
    }

    public function test_coupon_at_its_limit_is_refused(): void
    {
        $course = Course::factory()->published()->create();
        Coupon::factory()->create(['code' => 'ONCE', 'usage_limit' => 1]);
        $this->place(Student::factory()->create(), OrderItemType::Course, $course, 'ONCE');

        $error = $this->errorFrom(fn () => $this->place(Student::factory()->create(), OrderItemType::Course, $course, 'ONCE'), 'coupon');

        $this->assertSame('وصل كود الخصم إلى حد الاستخدام.', $error);
        $this->assertSame(1, Order::count());
    }

    public function test_expired_suspended_unknown_and_out_of_scope_coupons_are_refused(): void
    {
        $course = Course::factory()->published()->create();
        $student = Student::factory()->create();
        Coupon::factory()->expired()->create(['code' => 'OLD']);
        Coupon::factory()->create(['code' => 'OFF', 'is_active' => false]);
        Coupon::factory()->create(['code' => 'WS', 'scope' => CouponScope::Workshops]);
        Coupon::factory()->create(['code' => 'OTHER', 'scope' => CouponScope::Course, 'course_id' => Course::factory()]);

        $this->assertSame('انتهت صلاحية كود الخصم.', $this->errorFrom(fn () => $this->place($student, OrderItemType::Course, $course, 'OLD'), 'coupon'));
        $this->assertSame('كود الخصم موقوف.', $this->errorFrom(fn () => $this->place($student, OrderItemType::Course, $course, 'OFF'), 'coupon'));
        $this->assertSame('كود الخصم غير صحيح.', $this->errorFrom(fn () => $this->place($student, OrderItemType::Course, $course, 'NOPE'), 'coupon'));
        $this->assertSame('كود الخصم لا ينطبق على هذا المنتج.', $this->errorFrom(fn () => $this->place($student, OrderItemType::Course, $course, 'WS'), 'coupon'));
        $this->assertSame('كود الخصم لا ينطبق على هذا المنتج.', $this->errorFrom(fn () => $this->place($student, OrderItemType::Course, $course, 'OTHER'), 'coupon'));
    }

    public function test_course_coupon_applies_to_its_own_course(): void
    {
        $course = Course::factory()->published()->create(['price' => 100]);
        Coupon::factory()->create(['code' => 'NEXT20', 'scope' => CouponScope::Course, 'course_id' => $course->id]);

        $order = $this->place(Student::factory()->create(), OrderItemType::Course, $course, 'NEXT20');

        $this->assertSame('80.00', $order->total);
    }

    public function test_refuses_suspended_student_unpublished_course_and_double_enrolment(): void
    {
        $published = Course::factory()->published()->create();
        $student = Student::factory()->create();
        Enrollment::factory()->for($student)->for($published)->create();

        $this->assertSame('حساب الطالب موقوف.', $this->errorFrom(fn () => $this->place(Student::factory()->suspended()->create(), OrderItemType::Course, $published), 'item'));
        $this->assertSame('الدورة غير منشورة.', $this->errorFrom(fn () => $this->place($student, OrderItemType::Course, Course::factory()->create()), 'item'));
        $this->assertSame('الطالب مسجّل في هذه الدورة بالفعل.', $this->errorFrom(fn () => $this->place($student, OrderItemType::Course, $published), 'item'));
    }

    public function test_refuses_full_or_ended_workshop(): void
    {
        $full = Workshop::factory()->create(['seats' => 1]);
        Order::factory()->create(['item_type' => OrderItemType::Workshop, 'item_id' => $full->id]);
        $student = Student::factory()->create();

        $this->assertSame('لا توجد مقاعد متاحة في هذه الورشة.', $this->errorFrom(fn () => $this->place($student, OrderItemType::Workshop, $full), 'item'));
        $this->assertSame('انتهت هذه الورشة.', $this->errorFrom(fn () => $this->place($student, OrderItemType::Workshop, Workshop::factory()->past()->create()), 'item'));
    }

    public function test_failed_validation_does_not_spend_the_coupon(): void
    {
        $coupon = Coupon::factory()->create(['code' => 'SAFE']);

        $this->errorFrom(fn () => $this->place(Student::factory()->create(), OrderItemType::Course, Course::factory()->create(), 'SAFE'), 'item');

        $this->assertSame(0, $coupon->fresh()->times_used);
    }
}
