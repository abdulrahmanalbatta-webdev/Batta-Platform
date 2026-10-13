<?php

namespace Tests\Feature;

use App\Enums\ArticleStatus;
use App\Models\Article;
use App\Models\Tool;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class ImportContentCommandTest extends TestCase
{
    use RefreshDatabase;

    private string $path;

    protected function setUp(): void
    {
        parent::setUp();

        $this->path = storage_path('framework/testing/content');
        File::ensureDirectoryExists($this->path.'/articles');
        File::put($this->path.'/articles/start-laravel.md', "---\ntitle: كيف تبدأ Laravel\ncategory: tutorials\nexcerpt: \"خطوات عملية\"\nmeta_description: دليل للمبتدئين\n---\n\n## أول خطوة\n\nثبّت PHP.\n");
        File::put($this->path.'/articles/broken.md', "---\ntitle: بدون تصنيف\n---\nنص");
        File::put($this->path.'/tools.json', json_encode([
            ['category' => 'المحررات', 'name' => 'VS Code', 'short' => 'VS', 'color' => '#007ACC', 'why' => 'محرر خفيف بإضافات لكل شيء', 'since' => 2018, 'url' => 'https://code.visualstudio.com', 'is_affiliate' => true],
            ['category' => 'المحررات', 'name' => 'Bad', 'short' => 'TOOLONG', 'color' => 'blue', 'why' => '', 'since' => 1],
        ]));
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->path);

        parent::tearDown();
    }

    public function test_new_articles_and_tools_arrive_as_drafts_and_invalid_ones_are_skipped(): void
    {
        $owner = User::factory()->owner()->create();

        $this->artisan('content:import', ['--path' => $this->path])->expectsOutputToContain('Added 2 draft(s).')->assertSuccessful();

        $article = Article::query()->sole();
        $this->assertSame('start-laravel', $article->slug);
        $this->assertSame(ArticleStatus::Draft, $article->status);
        $this->assertSame($owner->id, $article->author_id);
        $this->assertSame('خطوات عملية', $article->excerpt);
        $this->assertStringStartsWith('## أول خطوة', $article->body);

        $tool = Tool::query()->sole();
        $this->assertSame('VS Code', $tool->name);
        $this->assertFalse($tool->is_published);
        $this->assertTrue($tool->is_affiliate);
        $this->assertSame('المحررات', $tool->category->name);
    }

    public function test_what_was_edited_in_the_dashboard_is_never_overwritten(): void
    {
        $this->artisan('content:import', ['--path' => $this->path])->assertSuccessful();
        Article::query()->sole()->update(['title' => 'عنواني المعدّل', 'status' => ArticleStatus::Published]);
        Tool::query()->sole()->update(['why' => 'سببي']);

        $this->artisan('content:import', ['--path' => $this->path])->expectsOutputToContain('Added 0 draft(s).')->assertSuccessful();

        $this->assertSame('عنواني المعدّل', Article::query()->sole()->title);
        $this->assertSame('سببي', Tool::query()->sole()->why);
    }
}
