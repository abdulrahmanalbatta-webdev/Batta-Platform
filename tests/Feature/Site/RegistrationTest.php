<?php

namespace Tests\Feature\Site;

use App\Enums\Role;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Student;
use App\Models\User;
use App\Models\Workshop;
use App\Models\WorkshopRegistration;
use App\Notifications\Alerts\RegistrationReceived;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, string>
     */
    private function signIn(Student $student): array
    {
        return ['Authorization' => 'Bearer '.$student->createToken('web')->plainTextToken];
    }

    public function test_a_guest_must_sign_in_to_register(): void
    {
        Course::factory()->published()->create(['slug' => 'laravel']);
        $workshop = Workshop::factory()->create();

        $this->postJson(route('site.me.courses.store', 'laravel'))->assertUnauthorized();
        $this->deleteJson(route('site.me.courses.destroy', 'laravel'))->assertUnauthorized();
        $this->getJson(route('site.me.workshops.index'))->assertUnauthorized();
        $this->postJson(route('site.me.workshops.store', $workshop))->assertUnauthorized();
        $this->deleteJson(route('site.me.workshops.destroy', $workshop))->assertUnauthorized();

        $this->assertSame(0, Enrollment::count() + WorkshopRegistration::count());
    }

    public function test_a_student_registers_in_a_published_course_and_the_team_is_alerted(): void
    {
        Notification::fake();
        $owner = User::factory()->owner()->create();
        $admin = User::factory()->admin()->create();
        $support = User::factory()->role(Role::Support)->create();
        $editor = User::factory()->role(Role::Editor)->create();
        $student = Student::factory()->create();
        $course = Course::factory()->published()->create(['slug' => 'laravel']);

        $response = $this->postJson(route('site.me.courses.store', 'laravel'), [], $this->signIn($student));

        $response->assertCreated()
            ->assertJsonPath('message', 'تم تسجيلك في الدورة.')
            ->assertJsonPath('data.slug', 'laravel')
            ->assertJsonPath('data.students', 1);
        $this->assertDatabaseHas('enrollments', ['student_id' => $student->id, 'course_id' => $course->id]);
        Notification::assertSentTo([$owner, $admin, $support], RegistrationReceived::class,
            fn (RegistrationReceived $alert): bool => $alert->student->is($student) && $alert->item->is($course));
        Notification::assertNotSentTo($editor, RegistrationReceived::class);
    }

    public function test_registering_twice_in_a_course_keeps_one_registration_and_alerts_once(): void
    {
        Notification::fake();
        $owner = User::factory()->owner()->create();
        $student = Student::factory()->create();
        Course::factory()->published()->create(['slug' => 'laravel']);
        $headers = $this->signIn($student);

        $this->postJson(route('site.me.courses.store', 'laravel'), [], $headers)->assertCreated();
        $this->postJson(route('site.me.courses.store', 'laravel'), [], $headers)
            ->assertOk()
            ->assertJsonPath('message', 'تم تسجيلك في الدورة.');

        $this->assertSame(1, Enrollment::count());
        Notification::assertSentToTimes($owner, RegistrationReceived::class, 1);
    }

    public function test_an_unpublished_course_cannot_be_registered_in(): void
    {
        $student = Student::factory()->create();
        Course::factory()->create(['slug' => 'draft-course']);

        $this->postJson(route('site.me.courses.store', 'draft-course'), [], $this->signIn($student))->assertNotFound();

        $this->assertSame(0, Enrollment::count());
    }

    public function test_a_student_cancels_their_course_registration(): void
    {
        $student = Student::factory()->create();
        $course = Course::factory()->published()->create(['slug' => 'laravel']);
        Enrollment::factory()->for($student)->for($course)->create();
        $other = Enrollment::factory()->for($course)->create();

        $this->deleteJson(route('site.me.courses.destroy', 'laravel'), [], $this->signIn($student))->assertNoContent();

        $this->assertDatabaseMissing('enrollments', ['student_id' => $student->id, 'course_id' => $course->id]);
        $this->assertModelExists($other);
    }

    public function test_my_courses_list_only_the_ones_i_registered_in(): void
    {
        $student = Student::factory()->create();
        Enrollment::factory()->for($student)->for(Course::factory()->published()->create(['title' => 'مسجّل فيها']))->create();
        Enrollment::factory()->for(Course::factory()->published()->create(['title' => 'لطالب آخر']))->create();
        Course::factory()->published()->create(['title' => 'لم يسجّل فيها أحد']);

        $response = $this->getJson(route('site.me.courses.index'), $this->signIn($student));

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'مسجّل فيها');
    }

    public function test_a_student_takes_a_seat_in_a_workshop_and_the_team_is_alerted(): void
    {
        Notification::fake();
        $owner = User::factory()->owner()->create();
        $editor = User::factory()->role(Role::Editor)->create();
        $student = Student::factory()->create();
        $workshop = Workshop::factory()->create(['seats' => 10]);

        $response = $this->postJson(route('site.me.workshops.store', $workshop), [], $this->signIn($student));

        $response->assertCreated()
            ->assertJsonPath('message', 'تم حجز مقعدك في الورشة.')
            ->assertJsonPath('data.id', $workshop->id)
            ->assertJsonPath('data.seats_left', 9);
        $this->assertDatabaseHas('workshop_registrations', ['student_id' => $student->id, 'workshop_id' => $workshop->id]);
        Notification::assertSentTo($owner, RegistrationReceived::class, fn (RegistrationReceived $alert): bool => $alert->item->is($workshop));
        Notification::assertNotSentTo($editor, RegistrationReceived::class);
    }

    public function test_taking_a_seat_twice_keeps_one_registration(): void
    {
        $student = Student::factory()->create();
        $workshop = Workshop::factory()->create();
        $headers = $this->signIn($student);

        $this->postJson(route('site.me.workshops.store', $workshop), [], $headers)->assertCreated();
        $this->postJson(route('site.me.workshops.store', $workshop), [], $headers)->assertOk();

        $this->assertSame(1, WorkshopRegistration::count());
    }

    public function test_a_full_workshop_refuses_new_seats(): void
    {
        $workshop = Workshop::factory()->create(['seats' => 1]);
        WorkshopRegistration::factory()->for($workshop)->create();
        $student = Student::factory()->create();

        $response = $this->postJson(route('site.me.workshops.store', $workshop), [], $this->signIn($student));

        $response->assertUnprocessable()->assertJsonValidationErrors(['workshop' => 'لا توجد مقاعد متاحة في هذه الورشة.']);
        $this->assertSame(1, $workshop->registrations()->count());
    }

    public function test_an_ended_workshop_refuses_seats_and_cancellations(): void
    {
        $workshop = Workshop::factory()->past()->create();
        $student = Student::factory()->create();
        $registration = WorkshopRegistration::factory()->for($workshop)->for($student)->create();
        $newcomer = Student::factory()->create();

        $this->postJson(route('site.me.workshops.store', $workshop), [], $this->signIn($newcomer))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['workshop' => 'انتهت هذه الورشة.']);
        $this->app['auth']->forgetGuards();
        $this->deleteJson(route('site.me.workshops.destroy', $workshop), [], $this->signIn($student))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['workshop' => 'انتهت هذه الورشة.']);

        $this->assertModelExists($registration);
        $this->assertSame(1, WorkshopRegistration::count());
    }

    public function test_a_student_gives_their_seat_back(): void
    {
        $workshop = Workshop::factory()->create();
        $student = Student::factory()->create();
        $registration = WorkshopRegistration::factory()->for($workshop)->for($student)->create();
        $other = WorkshopRegistration::factory()->for($workshop)->create();

        $this->deleteJson(route('site.me.workshops.destroy', $workshop), [], $this->signIn($student))->assertNoContent();

        $this->assertModelMissing($registration);
        $this->assertModelExists($other);
    }

    public function test_my_workshops_list_only_my_seats_soonest_first(): void
    {
        $student = Student::factory()->create();
        $later = Workshop::factory()->create(['date' => now()->addDays(20)->toDateString()]);
        $sooner = Workshop::factory()->create(['date' => now()->addDays(5)->toDateString()]);
        WorkshopRegistration::factory()->for($student)->for($later)->create();
        WorkshopRegistration::factory()->for($student)->for($sooner)->create();
        WorkshopRegistration::factory()->create();

        $response = $this->getJson(route('site.me.workshops.index'), $this->signIn($student));

        $response->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.*.id', [$sooner->id, $later->id]);
    }
}
