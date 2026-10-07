<?php

namespace Tests\Feature\Api;

use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Student;
use App\Models\User;
use App\Models\Workshop;
use App\Models\WorkshopRegistration;
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
        $student = Student::factory()->create(['created_at' => now()->subDays(60)]);
        Enrollment::factory()->for($student)->create(['created_at' => now()->subDay()]);
        WorkshopRegistration::factory()->for($student)->create(['created_at' => now()->subDays(3)]);
        Enrollment::factory()->for($student)->create(['created_at' => now()->subDays(10)]);
        Student::factory()->create(['created_at' => now()->subDays(2)]);

        $response = $this->actingAs(User::factory()->create())->getJson(route('api.analytics', ['days' => 7]));

        $response->assertOk()
            ->assertJsonPath('data.days', 7)
            ->assertJsonPath('data.kpis.registrations.value', 2)
            ->assertJsonPath('data.kpis.registrations.previous', 1)
            ->assertJsonPath('data.kpis.registrations.change', 100)
            ->assertJsonPath('data.kpis.enrollments.value', 1)
            ->assertJsonPath('data.kpis.enrollments.change', 0)
            ->assertJsonPath('data.kpis.workshop_registrations.value', 1)
            ->assertJsonPath('data.kpis.workshop_registrations.change', null)
            ->assertJsonPath('data.kpis.students.value', 1)
            ->assertJsonCount(7, 'data.series.labels')
            ->assertJsonPath('data.series.labels.6', '15 أكتوبر')
            ->assertJsonPath('data.series.registrations.5', 1)
            ->assertJsonPath('data.series.registrations.3', 1)
            ->assertJsonPath('data.series.students.4', 1);
    }

    public function test_a_year_is_shown_month_by_month(): void
    {
        Enrollment::factory()->create(['created_at' => now()->subMonths(2)]);

        $response = $this->actingAs(User::factory()->create())->getJson(route('api.analytics', ['days' => 365]));

        $response->assertJsonCount(12, 'data.series.labels')
            ->assertJsonPath('data.series.labels.11', 'أكتوبر')
            ->assertJsonPath('data.series.registrations.9', 1);
    }

    public function test_funnel_follows_the_students_who_joined_in_the_period(): void
    {
        [$once, $repeat] = Student::factory()->count(3)->create();
        $veteran = Student::factory()->create(['created_at' => now()->subDays(60)]);
        Enrollment::factory()->for($once)->create();
        Enrollment::factory()->for($repeat)->create();
        WorkshopRegistration::factory()->for($repeat)->create();
        Enrollment::factory()->for($veteran)->create();

        $response = $this->actingAs(User::factory()->create())->getJson(route('api.analytics'));

        $response->assertJsonPath('data.days', 30)
            ->assertJsonPath('data.funnel.*.label', ['حسابات جديدة', 'سجّلوا في دورة أو ورشة', 'سجّلوا في أكثر من واحدة'])
            ->assertJsonPath('data.funnel.*.value', [3, 2, 1]);
    }

    public function test_split_top_items_and_registrant_countries(): void
    {
        $saudi = Student::factory()->create(['country' => 'السعودية']);
        $jordan = Student::factory()->create(['country' => 'الأردن']);
        Student::factory()->create(['country' => 'مصر']);
        $course = Course::factory()->create(['title' => 'Next.js']);
        Enrollment::factory()->for($saudi)->for($course)->create();
        Enrollment::factory()->for($jordan)->for($course)->create();
        WorkshopRegistration::factory()->for($saudi)->for(Workshop::factory()->create(['title' => 'Git']))->create();

        $response = $this->actingAs(User::factory()->create())->getJson(route('api.analytics'));

        $response->assertJsonPath('data.split', [['label' => 'الدورات', 'value' => 2], ['label' => 'الورش', 'value' => 1]])
            ->assertJsonPath('data.top_items', [
                ['name' => 'Next.js', 'type_label' => 'دورة', 'registrations' => 2],
                ['name' => 'Git', 'type_label' => 'ورشة', 'registrations' => 1],
            ])
            ->assertJsonPath('data.countries', [['label' => 'السعودية', 'value' => 50], ['label' => 'الأردن', 'value' => 50]]);
    }

    public function test_only_known_periods(): void
    {
        $this->actingAs(User::factory()->create())->getJson(route('api.analytics', ['days' => 12]))->assertUnprocessable();
    }
}
