<?php

namespace Tests\Feature\Api;

use App\Enums\OrderStatus;
use App\Enums\Role;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Order;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StudentControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_lists_students_with_courses_spent_and_state(): void
    {
        $student = Student::factory()->pro()->create(['name' => 'ليان سالم']);
        Enrollment::factory()->count(2)->for($student)->create();
        Order::factory()->for($student)->create(['total' => 79]);
        Order::factory()->for($student)->create(['total' => 40, 'status' => OrderStatus::Refunded]);
        Student::factory()->suspended()->create();

        $response = $this->actingAs(User::factory()->role(Role::Accountant)->create())->getJson(route('api.students.index'));

        $row = collect($response->assertOk()->json('data'))->firstWhere('id', $student->id);
        $this->assertEquals(['courses' => 2, 'spent' => 79, 'is_pro' => true, 'state' => 'active', 'state_label' => 'نشط'],
            array_intersect_key($row, array_flip(['courses', 'spent', 'is_pro', 'state', 'state_label'])));
        $this->assertArrayNotHasKey('progress', $row);
        $this->assertContains('موقوف', collect($response->json('data'))->pluck('state_label'));
    }

    public function test_long_absent_student_is_inactive(): void
    {
        Student::factory()->inactive()->create();

        $response = $this->actingAs(User::factory()->create())->getJson(route('api.students.index'));

        $response->assertJsonPath('data.0.state', 'inactive');
    }

    public function test_profile_lists_courses_and_latest_orders(): void
    {
        $student = Student::factory()->create();
        $course = Course::factory()->published()->create();
        Enrollment::factory()->for($student)->for($course)->create();
        $order = Order::factory()->for($student)->create(['item_name' => 'Next.js']);

        $response = $this->actingAs(User::factory()->create())->getJson(route('api.students.show', $student));

        $response->assertOk()
            ->assertJsonPath('data.enrollments.0.title', $course->title)
            ->assertJsonPath('data.courses', 1)
            ->assertJsonPath('data.orders.0.number', '#'.(1000 + $order->id))
            ->assertJsonPath('data.orders.0.status_label', 'مكتمل');
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

    public function test_accountant_cannot_suspend_and_gets_403(): void
    {
        $student = Student::factory()->create();

        $this->actingAs(User::factory()->role(Role::Accountant)->create())
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
