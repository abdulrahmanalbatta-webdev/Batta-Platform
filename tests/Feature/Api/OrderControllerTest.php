<?php

namespace Tests\Feature\Api;

use App\Actions\Orders\PlaceOrder;
use App\Enums\OrderItemType;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\Role;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Order;
use App\Models\Student;
use App\Models\User;
use App\Notifications\OrderInvoice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class OrderControllerTest extends TestCase
{
    use RefreshDatabase;

    private function accountant(): User
    {
        return User::factory()->role(Role::Accountant)->create();
    }

    private function pendingCourseOrder(Student $student, Course $course): Order
    {
        return app(PlaceOrder::class)->handle($student, OrderItemType::Course, $course, PaymentMethod::BankTransfer);
    }

    public function test_lists_orders_newest_first_with_labels(): void
    {
        $older = Order::factory()->create(['created_at' => now()->subDay(), 'coupon_code' => 'LAUNCH30']);
        $newer = Order::factory()->pending()->create();

        $response = $this->actingAs(User::factory()->role(Role::Support)->create())->getJson(route('api.orders.index'));

        $response->assertOk()
            ->assertJsonPath('data.0.id', $newer->id)
            ->assertJsonPath('data.0.status_label', 'معلّق')
            ->assertJsonPath('data.1.number', '#'.(1000 + $older->id))
            ->assertJsonPath('data.1.item_type_label', 'دورة')
            ->assertJsonPath('data.1.payment_method_label', 'تحويل بنكي')
            ->assertJsonPath('data.1.coupon_code', 'LAUNCH30');
    }

    public function test_confirming_a_bank_transfer_enrols_the_student(): void
    {
        $student = Student::factory()->create();
        $course = Course::factory()->published()->create();
        $order = $this->pendingCourseOrder($student, $course);

        $response = $this->actingAs($this->accountant())->postJson(route('api.orders.payment.store', $order));

        $response->assertOk()->assertJsonPath('data.status', 'completed');
        $this->assertTrue($student->courses()->whereKey($course->id)->exists());
    }

    public function test_confirming_a_completed_order_again_returns_422(): void
    {
        $order = Order::factory()->create();

        $response = $this->actingAs($this->accountant())->postJson(route('api.orders.payment.store', $order));

        $response->assertUnprocessable()->assertJsonValidationErrors(['status' => 'يمكن تأكيد دفع الطلبات المعلّقة فقط.']);
    }

    public function test_refund_marks_order_refunded_and_removes_access(): void
    {
        $student = Student::factory()->create();
        $course = Course::factory()->published()->create();
        $order = $this->pendingCourseOrder($student, $course);
        $this->actingAs($this->accountant())->postJson(route('api.orders.payment.store', $order));

        $response = $this->postJson(route('api.orders.refund.store', $order));

        $response->assertOk()->assertJsonPath('data.status_label', 'مسترد');
        $this->assertFalse($student->courses()->whereKey($course->id)->exists());
    }

    public function test_failing_a_pending_order(): void
    {
        $order = Order::factory()->pending()->create();

        $response = $this->actingAs($this->accountant())->postJson(route('api.orders.failure.store', $order));

        $response->assertOk()->assertJsonPath('data.status', 'failed');
    }

    public function test_sends_invoice_to_the_student(): void
    {
        Notification::fake();
        $order = Order::factory()->create(['item_name' => 'Next.js']);

        $response = $this->actingAs($this->accountant())->postJson(route('api.orders.invoice.store', $order));

        $response->assertOk()->assertJsonPath('message', 'تم إرسال الفاتورة إلى '.$order->student->email);
        Notification::assertSentTo($order->student, OrderInvoice::class, function (OrderInvoice $invoice) use ($order) {
            return in_array('المنتج: Next.js (دورة)', $invoice->toMail($order->student)->introLines, true);
        });
    }

    public function test_no_invoice_for_failed_order(): void
    {
        $order = Order::factory()->create(['status' => OrderStatus::Failed]);

        $this->actingAs($this->accountant())->postJson(route('api.orders.invoice.store', $order))->assertUnprocessable();
    }

    public function test_editor_cannot_refund_and_gets_403(): void
    {
        $order = Order::factory()->create();

        $this->actingAs(User::factory()->role(Role::Editor)->create())->postJson(route('api.orders.refund.store', $order))->assertForbidden();

        $this->assertSame(OrderStatus::Completed, $order->fresh()->status);
    }

    public function test_a_manual_paid_order_enrols_the_student_at_once(): void
    {
        $student = Student::factory()->create();
        $course = Course::factory()->published()->create(['price' => 40]);

        $response = $this->actingAs(User::factory()->role(Role::Accountant)->create())->postJson(route('api.orders.store'), [
            'student_id' => $student->id,
            'item_type' => 'course',
            'item_id' => $course->id,
            'payment_method' => 'wallet',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.status', 'completed')
            ->assertJsonPath('data.payment_method_label', 'محفظة إلكترونية')
            ->assertJsonPath('data.total', 40);
        $this->assertTrue($student->canAccess($course));
    }

    public function test_a_manual_order_can_wait_for_the_payment_and_pro_needs_no_item(): void
    {
        $student = Student::factory()->create();
        $owner = User::factory()->owner()->create();

        $this->actingAs($owner)->postJson(route('api.orders.store'), [
            'student_id' => $student->id,
            'item_type' => 'pro-month',
            'payment_method' => 'cash',
            'paid' => false,
        ])->assertCreated()->assertJsonPath('data.status', 'pending');

        $this->assertFalse($student->fresh()->isPro());
        $this->actingAs($owner)->postJson(route('api.orders.payment.store', Order::query()->sole()))->assertOk();
        $this->assertTrue($student->fresh()->isPro());
    }

    public function test_a_manual_order_is_validated_and_kept_to_the_sales_roles(): void
    {
        $student = Student::factory()->create();
        $course = Course::factory()->published()->create();
        Order::factory()->for($student)->create(['item_type' => OrderItemType::Course, 'item_id' => $course->id, 'status' => OrderStatus::Completed]);
        Enrollment::factory()->for($student)->for($course)->create();
        $owner = User::factory()->owner()->create();

        $this->actingAs($owner)->postJson(route('api.orders.store'), ['student_id' => $student->id, 'item_type' => 'course', 'payment_method' => 'card'])
            ->assertJsonValidationErrors(['item_id', 'payment_method']);
        $this->actingAs($owner)->postJson(route('api.orders.store'), ['student_id' => $student->id, 'item_type' => 'course', 'item_id' => $course->id, 'payment_method' => 'cash'])
            ->assertUnprocessable();
        $this->actingAs(User::factory()->role(Role::Editor)->create())->postJson(route('api.orders.store'), ['student_id' => $student->id, 'item_type' => 'pro-month', 'payment_method' => 'cash'])
            ->assertForbidden();
    }
}
