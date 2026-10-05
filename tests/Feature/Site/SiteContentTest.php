<?php

namespace Tests\Feature\Site;

use App\Models\Article;
use App\Models\Course;
use App\Models\CourseModule;
use App\Models\Enrollment;
use App\Models\Lesson;
use App\Models\Review;
use App\Models\Student;
use App\Models\Tool;
use App\Models\ToolCategory;
use App\Models\Workshop;
use App\Support\PlatformSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SiteContentTest extends TestCase
{
    use RefreshDatabase;

    public function test_settings_never_include_secrets_and_list_the_project_services(): void
    {
        app(PlatformSettings::class)->update([
            'payment_instructions' => 'IBAN PS00 0000 ثم أرسل الإيصال على واتساب',
            'mail_password' => 'very-secret',
        ]);

        $response = $this->getJson(route('site.settings'));

        $response->assertOk()
            ->assertJsonPath('data.site_name', 'Batta')
            ->assertJsonPath('data.currency_symbol', '$')
            ->assertJsonPath('data.payment_instructions', 'IBAN PS00 0000 ثم أرسل الإيصال على واتساب')
            ->assertJsonPath('data.payment_methods.0', ['value' => 'bank-transfer', 'label' => 'تحويل بنكي'])
            ->assertJsonPath('data.project_services.0.value', 'websites')
            ->assertJsonMissingPath('data.invoice_note')
            ->assertDontSee('very-secret')
            ->assertCookieMissing(config('session.cookie'));
    }

    public function test_the_site_origin_may_call_the_api_cross_origin(): void
    {
        config(['cors.allowed_origins' => ['https://batta.dev']]);

        $this->getJson(route('site.courses.index'), ['Origin' => 'https://batta.dev'])
            ->assertOk()
            ->assertHeader('Access-Control-Allow-Origin', 'https://batta.dev');

        $this->assertNotSame('https://evil.test', $this->getJson(route('site.courses.index'), ['Origin' => 'https://evil.test'])->headers->get('Access-Control-Allow-Origin'));
    }

    public function test_courses_list_only_published_ones_with_their_numbers(): void
    {
        $course = Course::factory()->published()->create(['title' => 'Laravel من الصفر', 'price' => 49, 'outcomes' => ['مشروع منشور']]);
        Lesson::factory()->count(2)->for(CourseModule::factory()->for($course), 'module')->create(['duration_seconds' => 1800]);
        Enrollment::factory()->for($course)->create();
        Review::factory()->published()->for($course)->create(['rating' => 4]);
        Review::factory()->for($course)->create(['rating' => 1]);
        Course::factory()->create(['title' => 'مسودة']);

        $response = $this->getJson(route('site.courses.index'));

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'Laravel من الصفر')
            ->assertJsonPath('data.0.lessons', 2)
            ->assertJsonPath('data.0.hours', 1)
            ->assertJsonPath('data.0.students', 1)
            ->assertJsonPath('data.0.rating', 4)
            ->assertJsonPath('data.0.reviews', 1)
            ->assertJsonPath('data.0.outcomes', ['مشروع منشور'])
            ->assertJsonMissingPath('data.0.revenue')
            ->assertJsonMissingPath('data.0.status');
    }

    public function test_a_course_page_shows_the_curriculum_but_not_drafts(): void
    {
        $course = Course::factory()->published()->create(['slug' => 'laravel']);
        $module = CourseModule::factory()->for($course)->create(['title' => 'البداية']);
        Lesson::factory()->for($module, 'module')->create(['title' => 'التثبيت', 'duration_seconds' => 600]);
        Course::factory()->create(['slug' => 'draft-course']);

        $this->getJson(route('site.courses.show', 'laravel'))
            ->assertOk()
            ->assertJsonPath('data.modules.0.title', 'البداية')
            ->assertJsonPath('data.modules.0.lessons.0.title', 'التثبيت')
            ->assertJsonPath('data.modules.0.lessons.0.duration_seconds', 600);

        $this->getJson(route('site.courses.show', 'draft-course'))->assertNotFound();
    }

    public function test_course_reviews_show_published_ones_with_the_first_name_only(): void
    {
        $course = Course::factory()->published()->create(['slug' => 'laravel']);
        Review::factory()->published()->for($course)->for(Student::factory()->state(['name' => 'سارة أحمد', 'email' => 'sara@example.com']))->create();
        Review::factory()->hidden()->for($course)->create();

        $this->getJson(route('site.courses.reviews', 'laravel'))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'سارة')
            ->assertDontSee('sara@example.com');
    }

    public function test_workshops_are_upcoming_ones_with_the_seats_left(): void
    {
        Workshop::factory()->create(['title' => 'قادمة', 'date' => today()->addWeek(), 'seats' => 20]);
        Workshop::factory()->create(['title' => 'انتهت', 'date' => today()->subDay()]);

        $this->getJson(route('site.workshops.index'))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'قادمة')
            ->assertJsonPath('data.0.seats_left', 20);
    }

    public function test_articles_are_published_ones_and_reading_one_counts_a_view(): void
    {
        $article = Article::factory()->published()->create(['slug' => 'hello', 'body' => '<p>نص</p>']);
        Article::factory()->create(['slug' => 'draft']);
        Article::factory()->scheduled()->create();

        $this->getJson(route('site.articles.index'))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonMissingPath('data.0.body')
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('meta.per_page', 12);

        $this->getJson(route('site.articles.index', ['per_page' => 50]))->assertJsonPath('meta.per_page', 50);
        $this->getJson(route('site.articles.index', ['per_page' => 500]))->assertJsonValidationErrors('per_page');

        $this->getJson(route('site.articles.show', 'hello'))->assertOk()->assertJsonPath('data.body', '<p>نص</p>');
        $this->getJson(route('site.articles.show', 'draft'))->assertNotFound();

        $this->assertSame(1, $article->fresh()->views);
        $this->assertTrue($article->fresh()->updated_at->equalTo($article->updated_at));
    }

    public function test_tools_are_published_ones_grouped_and_clicks_are_counted(): void
    {
        $category = ToolCategory::factory()->create(['name' => 'المحررات']);
        $tool = Tool::factory()->for($category, 'category')->create(['name' => 'VS Code']);
        Tool::factory()->for($category, 'category')->draft()->create();
        Tool::factory()->draft()->create();

        $this->getJson(route('site.tools.index'))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'المحررات')
            ->assertJsonCount(1, 'data.0.tools')
            ->assertJsonMissingPath('data.0.tools.0.clicks');

        $this->postJson(route('site.tools.click', $tool))->assertNoContent();
        $this->assertSame(1, $tool->fresh()->clicks);
    }
}
