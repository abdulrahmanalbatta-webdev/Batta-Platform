<?php

namespace Tests\Feature\Api;

use App\Enums\CourseCategory;
use App\Enums\CourseLevel;
use App\Enums\CourseStatus;
use App\Enums\Role;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CourseControllerTest extends TestCase
{
    use RefreshDatabase;

    private function editor(): User
    {
        return User::factory()->role(Role::Editor)->create();
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return [
            'title' => 'Next.js من الصفر إلى الإنتاج',
            'slug' => null,
            'short_description' => 'دورة عملية',
            'description' => 'وصف',
            'outcomes' => ['بناء مشروع كامل'],
            'tags' => ['Next.js'],
            'level' => CourseLevel::Intermediate->value,
            'category' => CourseCategory::Frontend->value,
            'status' => CourseStatus::Draft->value,
            'publish_at' => null,
            'has_certificate' => true,
            'allows_questions' => true,
            ...$overrides,
        ];
    }

    public function test_lists_courses_without_their_descriptions(): void
    {
        $course = Course::factory()->create(['title' => 'APIs باستخدام Node']);

        $response = $this->actingAs(User::factory()->role(Role::Support)->create())->getJson(route('api.courses.index'));

        $response->assertOk()
            ->assertJsonPath('data.0.glyph', 'APIs')
            ->assertJsonPath('data.0.code', 'C-'.$course->id)
            ->assertJsonMissingPath('data.0.description')
            ->assertJsonMissingPath('data.0.lessons');
    }

    public function test_creates_course_with_slug_from_title(): void
    {
        $response = $this->actingAs($this->editor())->postJson(route('api.courses.store'), $this->payload());

        $response->assertCreated()
            ->assertJsonPath('data.slug', 'nextjs')
            ->assertJsonPath('data.outcomes', ['بناء مشروع كامل'])
            ->assertJsonMissingPath('data.modules');
    }

    public function test_a_course_publishes_without_any_lessons(): void
    {
        $this->actingAs($this->editor())->postJson(route('api.courses.store'), $this->payload(['status' => CourseStatus::Published->value]))
            ->assertCreated()->assertJsonPath('data.status', 'published');
    }

    public function test_invalid_slug_returns_422(): void
    {
        $response = $this->actingAs($this->editor())->postJson(route('api.courses.store'), $this->payload(['slug' => 'Bad Slug']));

        $response->assertUnprocessable()->assertJsonValidationErrors([
            'slug' => 'الرابط: حروف إنجليزية صغيرة وأرقام وشرطات فقط.',
        ]);
    }

    public function test_taken_slug_returns_422(): void
    {
        Course::factory()->create(['slug' => 'nextjs']);

        $response = $this->actingAs($this->editor())->postJson(route('api.courses.store'), $this->payload(['slug' => 'nextjs']));

        $response->assertUnprocessable()->assertJsonValidationErrors('slug');
    }

    public function test_status_switch_publishes_course(): void
    {
        $course = Course::factory()->create();

        $response = $this->actingAs($this->editor())->putJson(route('api.courses.status.update', $course), ['status' => 'published']);

        $response->assertOk()->assertJsonPath('data.status_label', 'منشورة');
    }

    public function test_copy_creates_draft(): void
    {
        $course = Course::factory()->published()->create(['title' => 'Git للفرق', 'slug' => 'git']);

        $response = $this->actingAs($this->editor())->postJson(route('api.courses.copies.store', $course));

        $response->assertCreated()
            ->assertJsonPath('data.title', 'Git للفرق (نسخة)')
            ->assertJsonPath('data.slug', 'git-copy')
            ->assertJsonPath('data.status', 'draft');
    }

    public function test_deletes_course(): void
    {
        $course = Course::factory()->create();

        $this->actingAs($this->editor())->deleteJson(route('api.courses.destroy', $course))->assertNoContent();

        $this->assertModelMissing($course);
    }

    public function test_support_cannot_change_status_and_gets_403(): void
    {
        $course = Course::factory()->create();

        $this->actingAs(User::factory()->role(Role::Support)->create())->putJson(route('api.courses.status.update', $course), ['status' => 'draft'])->assertForbidden();
    }

    public function test_list_counts_enrolled_students(): void
    {
        $course = Course::factory()->published()->create();
        Enrollment::factory()->count(2)->for($course)->create();

        $response = $this->actingAs($this->editor())->getJson(route('api.courses.index'));

        $response->assertJsonPath('data.0.students', 2)
            ->assertJsonMissingPath('data.0.revenue')
            ->assertJsonMissingPath('data.0.price');
    }

    public function test_course_with_students_cannot_be_deleted(): void
    {
        $course = Course::factory()->create();
        Enrollment::factory()->for($course)->create();

        $response = $this->actingAs($this->editor())->deleteJson(route('api.courses.destroy', $course));

        $response->assertUnprocessable()->assertJsonValidationErrors('course');
        $this->assertModelExists($course);
    }
}
