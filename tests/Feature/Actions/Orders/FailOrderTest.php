<?php

namespace Tests\Feature\Actions\Orders;

use App\Actions\Orders\FailOrder;
use App\Actions\Orders\PlaceOrder;
use App\Enums\OrderItemType;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Models\Coupon;
use App\Models\Course;
use App\Models\Student;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FailOrderTest extends TestCase
{
    use RefreshDatabase;

    public function test_failed_order_gives_the_coupon_use_back(): void
    {
        $coupon = Coupon::factory()->create(['code' => 'BACK', 'usage_limit' => 1]);
        $order = app(PlaceOrder::class)->handle(Student::factory()->create(), OrderItemType::Course, Course::factory()->published()->create(), PaymentMethod::Card, 'BACK');

        $order = app(FailOrder::class)->handle($order);

        $this->assertSame(OrderStatus::Failed, $order->status);
        $this->assertSame(0, $coupon->fresh()->times_used);
    }
}
