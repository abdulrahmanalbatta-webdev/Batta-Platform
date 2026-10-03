<?php

namespace Tests\Feature\Api;

use App\Enums\CouponScope;
use App\Enums\Role;
use App\Models\Coupon;
use App\Models\Course;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CouponControllerTest extends TestCase
{
    use RefreshDatabase;

    private function accountant(): User
    {
        return User::factory()->role(Role::Accountant)->create();
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return [
            'code' => 'launch30',
            'type' => 'percent',
            'value' => 30,
            'usage_limit' => 0,
            'expires_on' => now()->addMonth()->toDateString(),
            'scope' => CouponScope::AllCourses->value,
            'course_id' => null,
            ...$overrides,
        ];
    }

    public function test_lists_coupons_with_state_and_scope_label(): void
    {
        $course = Course::factory()->create(['title' => 'Next.js']);
        Coupon::factory()->expired()->create(['created_at' => now()->subDay()]);
        Coupon::factory()->create(['scope' => CouponScope::Course, 'course_id' => $course->id, 'is_active' => false]);

        $response = $this->actingAs(User::factory()->role(Role::Support)->create())->getJson(route('api.coupons.index'));

        $response->assertOk()
            ->assertJsonPath('data.0.scope_label', 'Next.js')
            ->assertJsonPath('data.0.state_label', 'موقوف')
            ->assertJsonPath('data.1.state', 'expired');
    }

    public function test_creates_coupon_with_upper_case_code_and_unlimited_uses(): void
    {
        $response = $this->actingAs($this->accountant())->postJson(route('api.coupons.store'), $this->payload());

        $response->assertCreated()
            ->assertJsonPath('data.code', 'LAUNCH30')
            ->assertJsonPath('data.usage_limit', null)
            ->assertJsonPath('data.state', 'active');
    }

    public function test_course_coupon_needs_a_course(): void
    {
        $response = $this->actingAs($this->accountant())->postJson(route('api.coupons.store'), $this->payload(['scope' => CouponScope::Course->value]));

        $response->assertUnprocessable()->assertJsonValidationErrors(['course_id' => 'اختر الدورة التي ينطبق عليها الكوبون.']);
    }

    public function test_non_course_coupon_drops_the_course(): void
    {
        $course = Course::factory()->create();

        $this->actingAs($this->accountant())->postJson(route('api.coupons.store'), $this->payload(['course_id' => $course->id]))->assertCreated();

        $this->assertNull(Coupon::sole()->course_id);
    }

    public function test_invalid_values_return_422(): void
    {
        Coupon::factory()->create(['code' => 'TAKEN']);

        $response = $this->actingAs($this->accountant())->postJson(route('api.coupons.store'), $this->payload([
            'code' => 'taken',
            'value' => 150,
            'expires_on' => now()->subDay()->toDateString(),
        ]));

        $response->assertUnprocessable()->assertJsonValidationErrors([
            'code' => 'هذا الكود موجود مسبقاً.',
            'value',
            'expires_on' => 'تاريخ الانتهاء يجب أن يكون اليوم أو بعده.',
        ]);
    }

    public function test_bad_code_format_returns_422(): void
    {
        $response = $this->actingAs($this->accountant())->postJson(route('api.coupons.store'), $this->payload(['code' => 'خصم 10']));

        $response->assertUnprocessable()->assertJsonValidationErrors(['code' => 'الكود: 3–20 حرفاً إنجليزياً أو رقماً.']);
    }

    public function test_switches_coupon_off(): void
    {
        $coupon = Coupon::factory()->create();

        $response = $this->actingAs($this->accountant())->patchJson(route('api.coupons.update', $coupon), ['is_active' => false]);

        $response->assertOk()->assertJsonPath('data.state', 'suspended');
    }

    public function test_deleting_coupon_keeps_code_on_its_orders(): void
    {
        $coupon = Coupon::factory()->create(['code' => 'GONE']);
        $order = Order::factory()->create(['coupon_id' => $coupon->id, 'coupon_code' => 'GONE']);

        $this->actingAs($this->accountant())->deleteJson(route('api.coupons.destroy', $coupon))->assertNoContent();

        $this->assertModelMissing($coupon);
        $this->assertNull($order->fresh()->coupon_id);
        $this->assertSame('GONE', $order->fresh()->coupon_code);
    }

    public function test_editor_cannot_create_coupons_and_gets_403(): void
    {
        $this->actingAs(User::factory()->role(Role::Editor)->create())->postJson(route('api.coupons.store'), $this->payload())->assertForbidden();
    }
}
