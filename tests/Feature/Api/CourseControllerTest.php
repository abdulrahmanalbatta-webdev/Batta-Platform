<?php

namespace Tests\Feature\Api;

use App\Enums\CourseCategory;
use App\Enums\CourseLevel;
use App\Enums\CourseStatus;
use App\Enums\OrderStatus;
use App\Enums\Role;
use App\Models\Course;
use App\Models\CourseModule;
use App\Models\Enrollment;
use App\Models\Lesson;
use App\Models\Order;
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
            'price' => 79,
            'old_price' => 99,
            'has_regional_pricing' => true,
            'is_included_in_pro' => false,
            'has_certificate' => true,
            'allows_questions' => true,
            'modules' => [
                ['id' => null, 'title' => 'البداية', 'lessons' => [
                    ['id' => null, 'title' => 'مقدمة', 'duration' => '05:20'],
                    ['id' => null, 'title' => 'التجهيز', 'duration' => '12:40'],
                ]],
            ],
            ...$overrides,
        ];
    }

    public function test_lists_courses_with_lesson_counts_and_hours(): void
    {
        $course = Course::factory()->create(['title' => 'APIs باستخدام Node']);
        $module = CourseModule::factory()->for($course)->create();
        Lesson::factory()->for($module, 'module')->count(2)->create(['duration_seconds' => 1800]);

        $response = $this->actingAs(User::factory()->role(Role::Support)->create())->getJson(route('api.courses.index'));

        $response->assertOk()
            ->assertJsonPath('data.0.lessons', 2)
            ->assertJsonPath('data.0.hours', 1)
            ->assertJsonPath('data.0.glyph', 'APIs')
            ->assertJsonPath('data.0.code', 'C-'.$course->id)
            ->assertJsonMissingPath('data.0.modules');
    }

    public function test_creates_course_with_curriculum_and_slug_from_title(): void
    {
        $response = $this->actingAs($this->editor())->postJson(route('api.courses.store'), $this->payload());

        $response->assertCreated()
            ->assertJsonPath('data.slug', 'nextjs')
            ->assertJsonPath('data.lessons', 2)
            ->assertJsonPath('data.modules.0.title', 'البداية')
            ->assertJsonPath('data.modules.0.lessons.1.duration', '12:40')
            ->assertJsonPath('data.outcomes', ['بناء مشروع كامل']);
        $this->assertSame(760, Lesson::where('title', 'التجهيز')->sole()->duration_seconds);
    }

    public function test_updating_curriculum_keeps_ids_of_existing_rows(): void
    {
        $course = Course::factory()->create();
        $module = CourseModule::factory()->for($course)->create(['title' => 'قديم']);
        $kept = Lesson::factory()->for($module, 'module')->create(['title' => 'درس باقٍ']);
        $removed = Lesson::factory()->for($module, 'module')->create(['title' => 'درس محذوف']);

        $response = $this->actingAs($this->editor())->putJson(route('api.courses.update', $course), $this->payload([
            'slug' => $course->slug,
            'modules' => [
                ['id' => $module->id, 'title' => 'معدّل', 'lessons' => [
                    ['id' => $kept->id, 'title' => 'درس باقٍ ومعدّل', 'duration' => '01:00'],
                    ['id' => null, 'title' => 'درس جديد', 'duration' => null],
                ]],
            ],
        ]));

        $response->assertOk()->assertJsonPath('data.modules.0.id', $module->id)->assertJsonPath('data.modules.0.lessons.0.id', $kept->id);
        $this->assertSame('درس باقٍ ومعدّل', $kept->fresh()->title);
        $this->assertModelMissing($removed);
        $this->assertSame(['درس باقٍ ومعدّل', 'درس جديد'], $module->lessons()->pluck('title')->all());
    }

    public function test_removing_a_module_deletes_its_lessons(): void
    {
        $course = Course::factory()->create();
        $module = CourseModule::factory()->for($course)->create();
        $lesson = Lesson::factory()->for($module, 'module')->create();

        $this->actingAs($this->editor())->putJson(route('api.courses.update', $course), $this->payload(['slug' => $course->slug, 'modules' => []]))->assertOk();

        $this->assertModelMissing($module);
        $this->assertModelMissing($lesson);
    }

    public function test_ids_from_another_course_are_treated_as_new_rows(): void
    {
        $other = CourseModule::factory()->create(['title' => 'وحدة دورة أخرى']);
        $course = Course::factory()->create();

        $this->actingAs($this->editor())->putJson(route('api.courses.update', $course), $this->payload([
            'slug' => $course->slug,
            'modules' => [['id' => $other->id, 'title' => 'محاولة', 'lessons' => []]],
        ]))->assertOk();

        $this->assertSame('وحدة دورة أخرى', $other->fresh()->title);
        $this->assertSame(1, $course->modules()->count());
    }

    public function test_publishing_without_lessons_returns_422(): void
    {
        $response = $this->actingAs($this->editor())->postJson(route('api.courses.store'), $this->payload([
            'status' => CourseStatus::Published->value,
            'modules' => [['id' => null, 'title' => 'فارغة', 'lessons' => [['id' => null, 'title' => '', 'duration' => null]]]],
        ]));

        $response->assertUnprocessable()->assertJsonValidationErrors(['modules' => 'أضف درساً واحداً على الأقل قبل النشر.']);
    }

    public function test_invalid_duration_slug_and_old_price_return_422(): void
    {
        $response = $this->actingAs($this->editor())->postJson(route('api.courses.store'), $this->payload([
            'slug' => 'Bad Slug',
            'old_price' => 50,
            'modules' => [['id' => null, 'title' => 'وحدة', 'lessons' => [['id' => null, 'title' => 'درس', 'duration' => '5 دقائق']]]],
        ]));

        $response->assertUnprocessable()->assertJsonValidationErrors([
            'slug' => 'الرابط: حروف إنجليزية صغيرة وأرقام وشرطات فقط.',
            'old_price' => 'السعر قبل الخصم يجب أن يكون أعلى من السعر الحالي.',
            'modules.0.lessons.0.duration' => 'مدة الدرس بصيغة دقائق:ثوانٍ، مثل 12:40.',
        ]);
    }

    public function test_taken_slug_returns_422(): void
    {
        Course::factory()->create(['slug' => 'nextjs']);

        $response = $this->actingAs($this->editor())->postJson(route('api.courses.store'), $this->payload(['slug' => 'nextjs']));

        $response->assertUnprocessable()->assertJsonValidationErrors('slug');
    }

    public function test_status_switch_publishes_course_with_lessons(): void
    {
        $course = Course::factory()->create();
        Lesson::factory()->for(CourseModule::factory()->for($course), 'module')->create();

        $response = $this->actingAs($this->editor())->putJson(route('api.courses.status.update', $course), ['status' => 'published']);

        $response->assertOk()->assertJsonPath('data.status_label', 'منشورة');
    }

    public function test_status_switch_refuses_to_publish_empty_course(): void
    {
        $course = Course::factory()->create();

        $response = $this->actingAs($this->editor())->putJson(route('api.courses.status.update', $course), ['status' => 'published']);

        $response->assertUnprocessable()->assertJsonValidationErrors('status');
        $this->assertSame(CourseStatus::Draft, $course->fresh()->status);
    }

    public function test_copy_creates_draft_with_curriculum(): void
    {
        $course = Course::factory()->published()->create(['title' => 'Git للفرق', 'slug' => 'git']);
        Lesson::factory()->for(CourseModule::factory()->for($course), 'module')->count(3)->create();

        $response = $this->actingAs($this->editor())->postJson(route('api.courses.copies.store', $course));

        $response->assertCreated()
            ->assertJsonPath('data.title', 'Git للفرق (نسخة)')
            ->assertJsonPath('data.slug', 'git-copy')
            ->assertJsonPath('data.status', 'draft')
            ->assertJsonPath('data.lessons', 3);
        $this->assertSame(3, $course->lessons()->count());
    }

    public function test_deletes_course_with_curriculum(): void
    {
        $course = Course::factory()->create();
        $lesson = Lesson::factory()->for(CourseModule::factory()->for($course), 'module')->create();

        $this->actingAs($this->editor())->deleteJson(route('api.courses.destroy', $course))->assertNoContent();

        $this->assertModelMissing($course);
        $this->assertModelMissing($lesson);
    }

    public function test_accountant_cannot_change_status_and_gets_403(): void
    {
        $course = Course::factory()->create();

        $this->actingAs(User::factory()->role(Role::Accountant)->create())->putJson(route('api.courses.status.update', $course), ['status' => 'draft'])->assertForbidden();
    }

    public function test_list_counts_students_and_paid_revenue(): void
    {
        $course = Course::factory()->published()->create();
        Enrollment::factory()->count(2)->for($course)->create();
        Order::factory()->create(['item_id' => $course->id, 'total' => 79]);
        Order::factory()->create(['item_id' => $course->id, 'total' => 50, 'status' => OrderStatus::Refunded]);

        $response = $this->actingAs($this->editor())->getJson(route('api.courses.index'));

        $response->assertJsonPath('data.0.students', 2)->assertJsonPath('data.0.revenue', 79);
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
