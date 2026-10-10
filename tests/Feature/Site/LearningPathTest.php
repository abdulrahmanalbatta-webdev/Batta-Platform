<?php

namespace Tests\Feature\Site;

use App\Models\Article;
use App\Models\Course;
use App\Models\LearningPath;
use App\Models\Workshop;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class LearningPathTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_site_starts_with_the_paths_it_had_on_the_site_content(): void
    {
        $this->getJson(route('site.paths.index'))
            ->assertOk()
            ->assertJsonPath('data.*.id', ['frontend', 'backend', 'programming-basics'])
            ->assertJsonPath('data.0.stages.0.title', 'أساسيات الويب: HTML و CSS')
            ->assertJsonMissingPath('data.0.stages.0.resources');

        $this->getJson(route('site.content'))->assertJsonMissingPath('data.paths');
    }

    public function test_paths_edited_on_the_site_content_move_over_as_they_were(): void
    {
        LearningPath::query()->delete();
        DB::table('site_blocks')->insert(['key' => 'paths', 'value' => json_encode([[
            'id' => 'data', 'title' => 'تحليل البيانات', 'icon' => 'search', 'summary' => 'ملخص', 'audience' => 'للجميع', 'duration' => 'شهران', 'outcomes' => ['تحليل'],
            'stages' => [['title' => 'Python', 'text' => 'نص', 'topics' => ['pandas'], 'resources' => [['title' => 'Docs', 'url' => 'https://docs.python.org', 'type' => 'docs', 'lang' => 'en']]]],
        ]])]);

        (require database_path('migrations/2026_10_10_100919_move_learning_paths_out_of_site_content.php'))->up();

        $path = LearningPath::query()->sole();
        $this->assertSame(['data', 'تحليل البيانات', true], [$path->slug, $path->title, $path->is_published]);
        $this->assertSame([], $path->stages[0]['items']);
        $this->assertSame('https://docs.python.org', $path->stages[0]['resources'][0]['url']);
        $this->assertDatabaseMissing('site_blocks', ['key' => 'paths']);
    }

    public function test_only_published_paths_are_listed_in_the_dashboards_order(): void
    {
        LearningPath::query()->delete();
        $second = LearningPath::factory()->create(['position' => 2]);
        $first = LearningPath::factory()->create(['position' => 1]);
        $hidden = LearningPath::factory()->hidden()->create(['position' => 0]);

        $this->getJson(route('site.paths.index'))
            ->assertOk()
            ->assertJsonPath('data.*.id', [$first->slug, $second->slug])
            ->assertJsonPath('data.0.resources_count', 1);

        $this->getJson(route('site.paths.show', $hidden->slug))->assertNotFound();
        $this->getJson(route('site.paths.show', 'nothing-here'))->assertNotFound();
    }

    public function test_a_path_page_shows_the_linked_content_the_site_may_show_in_order(): void
    {
        $course = Course::factory()->published()->create();
        $draftCourse = Course::factory()->create();
        $workshop = Workshop::factory()->create();
        $pastWorkshop = Workshop::factory()->past()->create();
        $article = Article::factory()->published()->create();
        $draftArticle = Article::factory()->create();
        $otherCourse = Course::factory()->published()->create();

        $path = LearningPath::factory()->create(['stages' => [[
            'title' => 'المرحلة الأولى',
            'text' => 'نص',
            'topics' => ['HTML'],
            'resources' => [['title' => 'MDN', 'url' => 'https://developer.mozilla.org', 'type' => 'docs', 'lang' => 'en']],
            'items' => [
                ['type' => 'course', 'id' => $otherCourse->id], ['type' => 'course', 'id' => $draftCourse->id], ['type' => 'course', 'id' => $course->id],
                ['type' => 'workshop', 'id' => $pastWorkshop->id], ['type' => 'workshop', 'id' => $workshop->id],
                ['type' => 'article', 'id' => $draftArticle->id], ['type' => 'article', 'id' => $article->id],
                ['type' => 'course', 'id' => 999],
            ],
        ]]]);

        $this->getJson(route('site.paths.show', $path->slug))
            ->assertOk()
            ->assertJsonPath('data.id', $path->slug)
            ->assertJsonPath('data.stages.0.resources.0.url', 'https://developer.mozilla.org')
            ->assertJsonPath('data.stages.0.courses.*.slug', [$otherCourse->slug, $course->slug])
            ->assertJsonPath('data.stages.0.workshops.*.id', [$workshop->id])
            ->assertJsonPath('data.stages.0.articles.*.slug', [$article->slug]);
    }
}
