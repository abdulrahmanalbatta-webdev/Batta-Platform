<?php

namespace Tests\Feature\Api;

use App\Enums\Role;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Student;
use App\Models\User;
use App\Models\Workshop;
use App\Models\WorkshopRegistration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StudentControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_lists_students_with_courses_workshops_and_state(): void
    {
        $student = Student::factory()->create(['name' => 'ليان سالم']);
        Enrollment::factory()->count(2)->for($student)->create();
        WorkshopRegistration::factory()->for($student)->create();
        Student::factory()->suspended()->create();

        $response = $this->actingAs(User::factory()->role(Role::Editor)->create())->getJson(route('api.students.index'));

        $row = collect($response->assertOk()->json('data'))->firstWhere('id', $student->id);
        $this->assertEquals(['courses' => 2, 'workshops' => 1, 'state' => 'active', 'state_label' => 'نشط'],
            array_intersect_key($row, array_flip(['courses', 'workshops', 'state', 'state_label'])));
        $this->assertArrayNotHasKey('progress', $row);
        $this->assertArrayNotHasKey('spent', $row);
        $this->assertArrayNotHasKey('is_pro', $row);
        $this->assertContains('موقوف', collect($response->json('data'))->pluck('state_label'));
    }

    public function test_long_absent_student_is_inactive(): void
    {
        Student::factory()->inactive()->create();

        $response = $this->actingAs(User::factory()->create())->getJson(route('api.students.index'));

        $response->assertJsonPath('data.0.state', 'inactive');
    }

    public function test_profile_lists_courses_and_workshops(): void
    {
        $student = Student::factory()->create();
        $course = Course::factory()->published()->create();
        $workshop = Workshop::factory()->create();
        Enrollment::factory()->for($student)->for($course)->create();
        WorkshopRegistration::factory()->for($student)->for($workshop)->create();

        $response = $this->actingAs(User::factory()->create())->getJson(route('api.students.show', $student));

        $response->assertOk()
            ->assertJsonPath('data.courses', 1)
            ->assertJsonPath('data.workshops', 1)
            ->assertJsonPath('data.enrollments.0.course_id', $course->id)
            ->assertJsonPath('data.enrollments.0.title', $course->title)
            ->assertJsonPath('data.workshop_registrations.0.workshop_id', $workshop->id)
            ->assertJsonPath('data.workshop_registrations.0.title', $workshop->title)
            ->assertJsonPath('data.workshop_registrations.0.workshop_date', $workshop->date->toDateString())
            ->assertJsonMissingPath('data.orders');
    }

    public function test_support_suspends_and_reactivates_students(): void
    {
        $students = Student::factory()->count(2)->create();
        $support = User::factory()->role(Role::Support)->create();

        $this->actingAs($support)->putJson(route('api.students.status.update'), ['ids' => $students->modelKeys(), 'status' => 'suspended'])
            ->assertOk()->assertJsonPath('updated', 2);
        $this->assertTrue($students[0]->fresh()->isSuspended());

        $this->putJson(route('api.students.status.update'), ['ids' => [$students[0]->id], 'status' => 'active'])->assertJsonPath('updated', 1);
        $this->assertFalse($students[0]->fresh()->isSuspended());
        $this->assertTrue($students[1]->fresh()->isSuspended());
    }

    public function test_editor_cannot_suspend_and_gets_403(): void
    {
        $student = Student::factory()->create();

        $this->actingAs(User::factory()->role(Role::Editor)->create())
            ->putJson(route('api.students.status.update'), ['ids' => [$student->id], 'status' => 'suspended'])
            ->assertForbidden();

        $this->assertFalse($student->fresh()->isSuspended());
    }

    public function test_invalid_status_returns_422(): void
    {
        $response = $this->actingAs(User::factory()->role(Role::Support)->create())
            ->putJson(route('api.students.status.update'), ['ids' => [], 'status' => 'deleted']);

        $response->assertUnprocessable()->assertJsonValidationErrors(['ids', 'status']);
    }
}
