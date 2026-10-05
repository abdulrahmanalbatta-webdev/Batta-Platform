<?php

namespace Tests\Feature\Site;

use App\Enums\ReviewStatus;
use App\Models\Course;
use App\Models\CourseModule;
use App\Models\Enrollment;
use App\Models\Lesson;
use App\Models\Review;
use App\Models\Student;
use App\Models\User;
use App\Notifications\Alerts\ReviewSubmitted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class StudentLearningTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{0: Course, 1: list<Lesson>}
     */
    private function courseWithLessons(array $attributes = [], int $lessons = 4): array
    {
        $course = Course::factory()->published()->create($attributes);
        $module = CourseModule::factory()->for($course)->create();

        return [$course, Lesson::factory()->count($lessons)->for($module, 'module')->create()->all()];
    }

    /**
     * @return array<string, string>
     */
    private function signIn(Student $student): array
    {
        return ['Authorization' => 'Bearer '.$student->createToken('web')->plainTextToken];
    }

    public function test_my_courses_are_the_bought_ones_and_pro_ones_with_progress(): void
    {
        $student = Student::factory()->pro()->create();
        [$bought, $lessons] = $this->courseWithLessons(['title' => 'مشتراة']);
        Enrollment::factory()->for($student)->for($bought)->create();
        $student->completions()->create(['lesson_id' => $lessons[0]->id, 'completed_at' => now()]);
        $this->courseWithLessons(['title' => 'ضمن Pro', 'is_included_in_pro' => true]);
        $this->courseWithLessons(['title' => 'ليست لي', 'is_included_in_pro' => false]);

        $response = $this->getJson(route('site.me.courses.index'), $this->signIn($student));

        $response->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.title', 'مشتراة')
            ->assertJsonPath('data.0.access', 'purchased')
            ->assertJsonPath('data.0.progress', 25)
            ->assertJsonPath('data.0.completed_lessons', [$lessons[0]->id])
            ->assertJsonPath('data.1.title', 'ضمن Pro')
            ->assertJsonPath('data.1.access', 'pro')
            ->assertJsonPath('data.1.progress', 0);
    }

    public function test_a_course_without_access_is_refused(): void
    {
        $this->getJson(route('site.me.courses.index'))->assertUnauthorized();

        $student = Student::factory()->create();
        [$course, $lessons] = $this->courseWithLessons(['slug' => 'laravel', 'is_included_in_pro' => true]);
        $headers = $this->signIn($student);

        $this->getJson(route('site.me.courses.show', 'laravel'), $headers)->assertForbidden();
        $this->postJson(route('site.me.lessons.complete', $lessons[0]), [], $headers)->assertForbidden();
        $this->putJson(route('site.me.courses.review', 'laravel'), ['rating' => 5, 'body' => 'دورة ممتازة جداً'], $headers)->assertForbidden();
    }

    public function test_completing_and_uncompleting_lessons_updates_progress(): void
    {
        $student = Student::factory()->inactive()->create();
        [$course, $lessons] = $this->courseWithLessons(['slug' => 'laravel']);
        Enrollment::factory()->for($student)->for($course)->create();
        $headers = $this->signIn($student);

        $this->postJson(route('site.me.lessons.complete', $lessons[0]), [], $headers)->assertOk()->assertJsonPath('data.progress', 25);
        $this->postJson(route('site.me.lessons.complete', $lessons[0]), [], $headers)->assertOk()->assertJsonPath('data.progress', 25);
        $this->postJson(route('site.me.lessons.complete', $lessons[1]), [], $headers)->assertOk()->assertJsonPath('data.progress', 50);
        $this->deleteJson(route('site.me.lessons.uncomplete', $lessons[0]), [], $headers)
            ->assertOk()
            ->assertJsonPath('data.progress', 25)
            ->assertJsonPath('data.completed_lessons', [$lessons[1]->id]);

        $this->assertSame('active', $student->fresh()->state());
        $this->getJson(route('site.me.courses.show', 'laravel'), $headers)
            ->assertOk()
            ->assertJsonPath('data.progress', 25)
            ->assertJsonPath('data.my_review', null)
            ->assertJsonCount(4, 'data.modules.0.lessons');
    }

    public function test_pro_access_ends_with_the_membership(): void
    {
        $student = Student::factory()->create(['pro_until' => now()->subDay()]);
        [, $lessons] = $this->courseWithLessons(['is_included_in_pro' => true]);

        $this->postJson(route('site.me.lessons.complete', $lessons[0]), [], $this->signIn($student))->assertForbidden();
    }

    public function test_a_review_waits_for_moderation_and_a_rewrite_waits_again(): void
    {
        Notification::fake();
        $owner = User::factory()->create();
        $student = Student::factory()->create();
        [$course] = $this->courseWithLessons(['slug' => 'laravel']);
        Enrollment::factory()->for($student)->for($course)->create();
        $headers = $this->signIn($student);

        $this->putJson(route('site.me.courses.review', 'laravel'), ['rating' => 5, 'body' => 'دورة ممتازة جداً'], $headers)
            ->assertCreated()
            ->assertJsonPath('data.status', 'pending');
        Notification::assertSentTo($owner, ReviewSubmitted::class);

        Review::query()->sole()->update(['status' => ReviewStatus::Published]);

        $this->putJson(route('site.me.courses.review', 'laravel'), ['rating' => 3, 'body' => 'غيّرت رأيي قليلاً'], $headers)
            ->assertOk()
            ->assertJsonPath('data.rating', 3)
            ->assertJsonPath('data.status', 'pending');
        $this->assertSame(1, Review::query()->count());

        $this->putJson(route('site.me.courses.review', 'laravel'), ['rating' => 9, 'body' => 'قصير'], $headers)
            ->assertJsonValidationErrors(['rating', 'body']);
    }
}
