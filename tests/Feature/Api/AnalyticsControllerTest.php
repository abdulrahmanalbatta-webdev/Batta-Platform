<?php

namespace Tests\Feature\Api;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Models\Order;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AnalyticsControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(now()->setDate(2026, 10, 15)->setTime(12, 0));
    }

    public function test_period_against_the_one_before_it(): void
    {
        Order::factory()->create(['total' => 100, 'paid_at' => now()->subDay()]);
        Order::factory()->create(['total' => 50, 'paid_at' => now()->subDays(3)]);
        Order::factory()->create(['total' => 60, 'paid_at' => now()->subDays(10)]);
        Order::factory()->pending()->create();

        $response = $this->actingAs(User::factory()->create())->getJson(route('api.analytics', ['days' => 7]));

        $response->assertOk()
            ->assertJsonPath('data.days', 7)
            ->assertJsonPath('data.kpis.revenue.value', 150)
            ->assertJsonPath('data.kpis.revenue.previous', 60)
            ->assertJsonPath('data.kpis.revenue.change', 150)
            ->assertJsonPath('data.kpis.orders.value', 2)
            ->assertJsonPath('data.kpis.average_order.value', 75)
            ->assertJsonCount(7, 'data.series.labels')
            ->assertJsonPath('data.series.labels.6', '15 أكتوبر')
            ->assertJsonPath('data.series.revenue.5', 100);
    }

    public function test_a_year_is_shown_month_by_month(): void
    {
        Order::factory()->create(['total' => 30, 'paid_at' => now()->subMonths(2)]);

        $response = $this->actingAs(User::factory()->create())->getJson(route('api.analytics', ['days' => 365]));

        $response->assertJsonCount(12, 'data.series.labels')
            ->assertJsonPath('data.series.labels.11', 'أكتوبر')
            ->assertJsonPath('data.series.revenue.9', 30);
    }

    public function test_funnel_follows_the_students_who_joined_in_the_period(): void
    {
        [$buyer, $repeat, $abandoned] = Student::factory()->count(3)->create();
        Student::factory()->create(['created_at' => now()->subDays(60)]);
        Order::factory()->for($buyer)->create();
        Order::factory()->count(2)->for($repeat)->create();
        Order::factory()->for($abandoned)->create(['status' => OrderStatus::Failed, 'paid_at' => null]);

        $response = $this->actingAs(User::factory()->create())->getJson(route('api.analytics'));

        $response->assertJsonPath('data.days', 30)
            ->assertJsonPath('data.funnel.*.value', [3, 3, 2, 1]);
    }

    public function test_payment_methods_products_and_buyer_countries(): void
    {
        $saudi = Student::factory()->create(['country' => 'السعودية']);
        $jordan = Student::factory()->create(['country' => 'الأردن']);
        Order::factory()->for($saudi)->count(2)->create(['item_name' => 'Next.js', 'total' => 79]);
        Order::factory()->for($jordan)->create(['item_name' => 'Git', 'total' => 29, 'payment_method' => PaymentMethod::PayPal]);

        $response = $this->actingAs(User::factory()->create())->getJson(route('api.analytics'));

        $response->assertJsonPath('data.payment_methods', [['label' => 'بطاقة', 'value' => 2], ['label' => 'PayPal', 'value' => 1]])
            ->assertJsonPath('data.top_products.0', ['name' => 'Next.js', 'type_label' => 'دورة', 'orders' => 2, 'revenue' => 158])
            ->assertJsonPath('data.countries', [['label' => 'السعودية', 'value' => 50], ['label' => 'الأردن', 'value' => 50]]);
    }

    public function test_only_known_periods(): void
    {
        $this->actingAs(User::factory()->create())->getJson(route('api.analytics', ['days' => 12]))->assertUnprocessable();
    }
}
