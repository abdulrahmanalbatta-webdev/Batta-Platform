<?php

namespace Tests\Feature\Api;

use App\Enums\OrderStatus;
use App\Enums\Role;
use App\Models\Course;
use App\Models\CourseModule;
use App\Models\Enrollment;
use App\Models\Lesson;
use App\Models\LessonCompletion;
use App\Models\Order;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StudentControllerTest extends TestCase
{
    use RefreshDatabase;

    /**
     * A course with the given number of lessons.
     */
    private function courseWithLessons(int $lessons): Course
    {
        $course = Course::factory()->published()->create();
        Lesson::factory()->count($lessons)->for(CourseModule::factory()->for($course), 'module')->create();

        return $course;
    }

    private function complete(Student $student, Course $course, int $lessons): void
    {
        $course->lessons()->limit($lessons)->get()->each(
            fn (Lesson $lesson) => LessonCompletion::factory()->for($student)->for($lesson)->create(),
        );
    }

    public function test_lists_students_with_courses_progress_spent_and_state(): void
    {
        $student = Student::factory()->pro()->create(['name' => 'ليان سالم']);
        $half = $this->courseWithLessons(4);
        $done = $this->courseWithLessons(2);
        Enrollment::factory()->for($student)->for($half)->create();
        Enrollment::factory()->for($student)->for($done)->create();
        $this->complete($student, $half, 2);
        $this->complete($student, $done, 2);
        Order::factory()->for($student)->create(['total' => 79]);
        Order::factory()->for($student)->create(['total' => 40, 'status' => OrderStatus::Refunded]);
        Student::factory()->suspended()->create();

        $response = $this->actingAs(User::factory()->role(Role::Accountant)->create())->getJson(route('api.students.index'));

        $row = collect($response->assertOk()->json('data'))->firstWhere('id', $student->id);
        $this->assertEquals(['courses' => 2, 'progress' => 75, 'spent' => 79, 'is_pro' => true, 'state' => 'active', 'state_label' => 'نشط'],
            array_intersect_key($row, array_flip(['courses', 'progress', 'spent', 'is_pro', 'state', 'state_label'])));
        $this->assertContains('موقوف', collect($response->json('data'))->pluck('state_label'));
    }

    public function test_long_absent_student_is_inactive(): void
    {
        Student::factory()->inactive()->create();

        $response = $this->actingAs(User::factory()->create())->getJson(route('api.students.index'));

        $response->assertJsonPath('data.0.state', 'inactive');
    }

    public function test_profile_lists_courses_with_progress_and_latest_orders(): void
    {
        $student = Student::factory()->create();
        $course = $this->courseWithLessons(4);
        Enrollment::factory()->for($student)->for($course)->create();
        $this->complete($student, $course, 1);
        $order = Order::factory()->for($student)->create(['item_name' => 'Next.js']);

        $response = $this->actingAs(User::factory()->create())->getJson(route('api.students.show', $student));

        $response->assertOk()
            ->assertJsonPath('data.enrollments.0.title', $course->title)
            ->assertJsonPath('data.enrollments.0.progress', 25)
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
