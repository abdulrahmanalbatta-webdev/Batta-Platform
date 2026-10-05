<?php

namespace Tests\Feature\Api;

use App\Enums\LeadStage;
use App\Enums\OrderItemType;
use App\Enums\OrderStatus;
use App\Models\Course;
use App\Models\CourseModule;
use App\Models\Enrollment;
use App\Models\Lead;
use App\Models\Lesson;
use App\Models\LessonCompletion;
use App\Models\Order;
use App\Models\Student;
use App\Models\User;
use App\Models\Workshop;
use App\Support\DashboardSummary;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class DashboardControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(now()->setDate(2026, 10, 15)->setTime(12, 0));
    }

    public function test_revenue_this_month_against_the_same_days_of_last_month(): void
    {
        Order::factory()->create(['total' => 100, 'paid_at' => now()->subDays(2)]);
        Order::factory()->create(['total' => 50, 'paid_at' => now()->subMonthNoOverflow()->subDays(3)]);
        // after the same day last month: not part of the comparison
        Order::factory()->create(['total' => 999, 'paid_at' => now()->subMonthNoOverflow()->addDays(5)]);
        Order::factory()->create(['total' => 70, 'status' => OrderStatus::Refunded, 'paid_at' => now()->subDay()]);
        Lead::factory()->stage(LeadStage::Won)->create(['budget' => 400]);

        $response = $this->actingAs(User::factory()->create())->getJson(route('api.dashboard'));

        $response->assertOk()
            ->assertJsonPath('data.month', 'أكتوبر')
            ->assertJsonPath('data.kpis.revenue.value', 500)
            ->assertJsonPath('data.kpis.revenue.previous', 50)
            ->assertJsonPath('data.kpis.revenue.change', 900)
            ->assertJsonPath('data.kpis.orders.value', 1)
            ->assertJsonPath('data.revenue.labels.11', 'أكتوبر')
            ->assertJsonPath('data.revenue.courses.11', 100)
            ->assertJsonPath('data.revenue.courses.10', 1049)
            ->assertJsonPath('data.revenue.services.11', 400);
    }

    public function test_change_is_null_without_a_previous_value(): void
    {
        Student::factory()->create();

        $response = $this->actingAs(User::factory()->create())->getJson(route('api.dashboard'));

        $response->assertJsonPath('data.kpis.students.value', 1)
            ->assertJsonPath('data.kpis.students.change', null)
            ->assertJsonCount(12, 'data.kpis.students.spark');
    }

    public function test_completion_is_the_average_progress_over_enrolments(): void
    {
        $course = Course::factory()->create();
        $lessons = Lesson::factory()->count(4)->for(CourseModule::factory()->for($course), 'module')->create();
        [$done, $started] = Student::factory()->count(2)->create();
        Enrollment::factory()->for($done)->for($course)->create();
        Enrollment::factory()->for($started)->for($course)->create();
        $lessons->each(fn (Lesson $lesson) => LessonCompletion::factory()->for($done)->for($lesson)->create(['completed_at' => now()]));
        LessonCompletion::factory()->for($started)->for($lessons[0])->create(['completed_at' => now()]);

        $response = $this->actingAs(User::factory()->create())->getJson(route('api.dashboard'));

        // (100% + 25%) / 2
        $response->assertJsonPath('data.kpis.completion', 63)->assertJsonPath('data.kpis.lessons.value', 5);
    }

    public function test_sales_mix_top_courses_workshops_pipeline_and_recent_orders(): void
    {
        $course = Course::factory()->create(['title' => 'Next.js']);
        Order::factory()->create(['item_id' => $course->id, 'total' => 80, 'paid_at' => now()->subDays(3)]);
        Order::factory()->create(['item_type' => OrderItemType::ProMonth, 'item_id' => null, 'total' => 9, 'paid_at' => now()->subDays(40)]);
        Workshop::factory()->past()->create();
        $next = Workshop::factory()->create(['date' => now()->addDays(5)->toDateString(), 'seats' => 20]);
        Lead::factory()->count(2)->create(['budget' => 1000]);
        Lead::factory()->stage(LeadStage::Lost)->create(['budget' => 5000]);

        $response = $this->actingAs(User::factory()->create())->getJson(route('api.dashboard'));

        $response->assertJsonPath('data.sales_mix.0', ['key' => 'courses', 'label' => 'الدورات', 'value' => 80])
            ->assertJsonPath('data.sales_mix.2.value', 0)
            ->assertJsonPath('data.top_courses.0.title', 'Next.js')
            ->assertJsonPath('data.top_courses.0.glyph', 'Next.js')
            ->assertJsonCount(1, 'data.upcoming_workshops')
            ->assertJsonPath('data.upcoming_workshops.0.id', $next->id)
            ->assertJsonPath('data.pipeline.value', 2000)
            ->assertJsonPath('data.pipeline.stages.0', ['key' => 'new', 'label' => 'جديد', 'count' => 2])
            ->assertJsonCount(2, 'data.recent_orders')
            ->assertJsonPath('data.totals.course_revenue', 89);
    }

    public function test_aggregates_are_cached_for_a_few_minutes(): void
    {
        $member = User::factory()->create();
        $this->actingAs($member)->getJson(route('api.dashboard'))->assertJsonPath('data.kpis.orders.value', 0);

        Order::factory()->create();

        $this->actingAs($member)->getJson(route('api.dashboard'))->assertJsonPath('data.kpis.orders.value', 0)->assertJsonCount(1, 'data.recent_orders');
        Cache::forget(DashboardSummary::CACHE_KEY);
        $this->actingAs($member)->getJson(route('api.dashboard'))->assertJsonPath('data.kpis.orders.value', 1);
    }

    public function test_winning_a_request_records_when_it_was_decided(): void
    {
        $lead = Lead::factory()->create();

        $lead->update(['stage' => LeadStage::Won]);
        $this->assertTrue($lead->decided_at->equalTo(now()));

        $lead->update(['stage' => LeadStage::Proposal]);
        $this->assertNull($lead->decided_at);
    }
}
