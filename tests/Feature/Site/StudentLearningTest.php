<?php

namespace Tests\Feature\Site;

use App\Enums\ReviewStatus;
use App\Models\Course;
use App\Models\Enrollment;
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

    private function course(array $attributes = []): Course
    {
        return Course::factory()->published()->create($attributes);
    }

    /**
     * @return array<string, string>
     */
    private function signIn(Student $student): array
    {
        return ['Authorization' => 'Bearer '.$student->createToken('web')->plainTextToken];
    }

    public function test_my_courses_are_the_ones_i_registered_in_newest_first(): void
    {
        $student = Student::factory()->create();
        Enrollment::factory()->for($student)->for($this->course(['title' => 'الأولى']))->create(['created_at' => now()->subDay()]);
        Enrollment::factory()->for($student)->for($this->course(['title' => 'الأحدث']))->create();
        $this->course(['title' => 'ليست لي']);

        $response = $this->getJson(route('site.me.courses.index'), $this->signIn($student));

        $response->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.title', 'الأحدث')
            ->assertJsonPath('data.1.title', 'الأولى')
            ->assertJsonMissingPath('data.0.access')
            ->assertJsonMissingPath('data.0.progress');
    }

    public function test_a_course_without_access_is_refused(): void
    {
        $this->getJson(route('site.me.courses.index'))->assertUnauthorized();

        $student = Student::factory()->create();
        $this->course(['slug' => 'laravel']);
        $headers = $this->signIn($student);

        $this->getJson(route('site.me.courses.show', 'laravel'), $headers)->assertForbidden();
        $this->putJson(route('site.me.courses.review', 'laravel'), ['rating' => 5, 'body' => 'دورة ممتازة جداً'], $headers)->assertForbidden();
    }

    public function test_an_enrolled_student_reads_their_course_and_review(): void
    {
        $student = Student::factory()->create();
        Enrollment::factory()->for($student)->for($this->course(['slug' => 'laravel']))->create();

        $this->getJson(route('site.me.courses.show', 'laravel'), $this->signIn($student))
            ->assertOk()
            ->assertJsonPath('data.slug', 'laravel')
            ->assertJsonPath('data.my_review', null);
    }

    public function test_a_review_waits_for_moderation_and_a_rewrite_waits_again(): void
    {
        Notification::fake();
        $owner = User::factory()->create();
        $student = Student::factory()->create();
        $course = $this->course(['slug' => 'laravel']);
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
