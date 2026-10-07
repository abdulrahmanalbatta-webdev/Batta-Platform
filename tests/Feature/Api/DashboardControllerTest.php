<?php

namespace Tests\Feature\Api;

use App\Enums\LeadStage;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Lead;
use App\Models\Student;
use App\Models\User;
use App\Models\Workshop;
use App\Models\WorkshopRegistration;
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

    public function test_registrations_this_month_against_the_same_days_of_last_month(): void
    {
        Enrollment::factory()->count(2)->create(['created_at' => now()->subDays(2)]);
        Enrollment::factory()->create(['created_at' => now()->subMonthNoOverflow()->subDays(3)]);
        // after the same day last month: not part of the comparison
        Enrollment::factory()->create(['created_at' => now()->subMonthNoOverflow()->addDays(5)]);
        WorkshopRegistration::factory()->create(['created_at' => now()->subDay()]);
        Lead::factory()->create();

        $response = $this->actingAs(User::factory()->create())->getJson(route('api.dashboard'));

        $response->assertOk()
            ->assertJsonPath('data.month', 'أكتوبر')
            ->assertJsonPath('data.previous_month', 'سبتمبر')
            ->assertJsonPath('data.kpis.enrollments.value', 2)
            ->assertJsonPath('data.kpis.enrollments.previous', 1)
            ->assertJsonPath('data.kpis.enrollments.change', 100)
            ->assertJsonPath('data.kpis.workshop_registrations.value', 1)
            ->assertJsonPath('data.kpis.workshop_registrations.change', null)
            ->assertJsonPath('data.kpis.leads.value', 1)
            ->assertJsonPath('data.registrations.labels.11', 'أكتوبر')
            ->assertJsonPath('data.registrations.courses.11', 2)
            ->assertJsonPath('data.registrations.courses.10', 2)
            ->assertJsonPath('data.registrations.workshops.11', 1)
            ->assertJsonMissingPath('data.kpis.revenue');
    }

    public function test_change_is_null_without_a_previous_value(): void
    {
        Student::factory()->create();

        $response = $this->actingAs(User::factory()->create())->getJson(route('api.dashboard'));

        $response->assertJsonPath('data.kpis.students.value', 1)
            ->assertJsonPath('data.kpis.students.change', null)
            ->assertJsonCount(12, 'data.kpis.students.spark');
    }

    public function test_enrollments_counts_this_months_course_registrations(): void
    {
        Enrollment::factory()->count(2)->create(['created_at' => now()]);
        Enrollment::factory()->create(['created_at' => now()->subMonths(2)]);

        $response = $this->actingAs(User::factory()->create())->getJson(route('api.dashboard'));

        $response->assertJsonPath('data.kpis.enrollments.value', 2)
            ->assertJsonCount(12, 'data.kpis.enrollments.spark')
            ->assertJsonMissingPath('data.kpis.completion');
    }

    public function test_registrations_mix_top_courses_workshops_pipeline_and_recent_registrations(): void
    {
        $nextJs = Course::factory()->published()->create(['title' => 'Next.js']);
        $git = Course::factory()->create(['title' => 'Git']);
        Enrollment::factory()->for($nextJs)->create(['created_at' => now()->subDays(3)]);
        Enrollment::factory()->for($nextJs)->create(['created_at' => now()->subDays(40)]);
        $latest = Enrollment::factory()->for($git)->create(['created_at' => now()]);
        Workshop::factory()->past()->create();
        $next = Workshop::factory()->create(['date' => now()->addDays(5)->toDateString(), 'seats' => 20]);
        WorkshopRegistration::factory()->for($next)->create(['created_at' => now()->subDay()]);
        Lead::factory()->count(2)->create(['budget' => 1000]);
        Lead::factory()->stage(LeadStage::Lost)->create(['budget' => 5000]);

        $response = $this->actingAs(User::factory()->create())->getJson(route('api.dashboard'));

        $response->assertJsonPath('data.registrations_mix', [
            ['key' => 'courses', 'label' => 'الدورات', 'value' => 2],
            ['key' => 'workshops', 'label' => 'الورش', 'value' => 1],
        ])
            ->assertJsonPath('data.top_courses.0', ['id' => $nextJs->id, 'title' => 'Next.js', 'glyph' => 'Next.js', 'students' => 2])
            ->assertJsonCount(2, 'data.top_courses')
            ->assertJsonCount(1, 'data.upcoming_workshops')
            ->assertJsonPath('data.upcoming_workshops.0.id', $next->id)
            ->assertJsonPath('data.upcoming_workshops.0.taken', 1)
            ->assertJsonPath('data.pipeline.value', 2000)
            ->assertJsonPath('data.pipeline.stages.0', ['key' => 'new', 'label' => 'جديد', 'count' => 2])
            ->assertJsonCount(4, 'data.recent_registrations')
            ->assertJsonPath('data.recent_registrations.0.type', 'course')
            ->assertJsonPath('data.recent_registrations.0.type_label', 'دورة')
            ->assertJsonPath('data.recent_registrations.0.title', 'Git')
            ->assertJsonPath('data.recent_registrations.0.student.email', $latest->student->email)
            ->assertJsonPath('data.recent_registrations.1.type', 'workshop')
            ->assertJsonPath('data.recent_registrations.1.type_label', 'ورشة')
            ->assertJsonPath('data.totals.enrollments', 3)
            ->assertJsonPath('data.totals.published_courses', 1)
            ->assertJsonMissingPath('data.recent_orders');
    }

    public function test_aggregates_are_cached_for_a_few_minutes(): void
    {
        $member = User::factory()->create();
        $this->actingAs($member)->getJson(route('api.dashboard'))->assertJsonPath('data.kpis.enrollments.value', 0);

        Enrollment::factory()->create();

        $this->actingAs($member)->getJson(route('api.dashboard'))->assertJsonPath('data.kpis.enrollments.value', 0)->assertJsonCount(1, 'data.recent_registrations');
        Cache::forget(DashboardSummary::CACHE_KEY);
        $this->actingAs($member)->getJson(route('api.dashboard'))->assertJsonPath('data.kpis.enrollments.value', 1);
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
