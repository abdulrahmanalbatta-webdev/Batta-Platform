<?php

namespace Tests\Feature;

use App\Enums\ArticleStatus;
use App\Jobs\RebuildSite;
use App\Models\Article;
use App\Models\Tool;
use App\Support\PlatformSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class RebuildSiteTest extends TestCase
{
    use RefreshDatabase;

    public function test_publishing_an_article_rebuilds_the_site_but_draft_edits_do_not(): void
    {
        app(PlatformSettings::class)->update(['site_rebuild_token' => 'github_pat_test']);
        Queue::fake();

        $article = Article::factory()->create(['status' => ArticleStatus::Draft]);
        $article->update(['title' => 'مسودة معدّلة']);
        Queue::assertNotPushed(RebuildSite::class);

        $article->update(['status' => ArticleStatus::Published]);
        Queue::assertPushed(RebuildSite::class);
    }

    public function test_taking_an_article_off_the_site_rebuilds_it(): void
    {
        $article = Article::factory()->create(['status' => ArticleStatus::Published]);
        app(PlatformSettings::class)->update(['site_rebuild_token' => 'github_pat_test']);
        Queue::fake();

        $article->update(['status' => ArticleStatus::Draft]);
        Queue::assertPushed(RebuildSite::class);
    }

    public function test_other_content_rebuilds_the_site_only_once_a_token_is_saved(): void
    {
        Queue::fake();
        Tool::factory()->create();
        Queue::assertNotPushed(RebuildSite::class);

        app(PlatformSettings::class)->update(['site_rebuild_token' => 'github_pat_test']);
        Tool::factory()->create();
        Queue::assertPushed(RebuildSite::class);
    }

    public function test_the_job_runs_the_site_deploy_workflow_with_the_saved_token(): void
    {
        app(PlatformSettings::class)->update(['site_rebuild_token' => 'github_pat_test']);
        Http::fake(['api.github.com/*' => Http::response(null, 204)]);

        app()->call([new RebuildSite, 'handle']);

        Http::assertSent(fn ($request) => $request->url() === 'https://api.github.com/repos/abdulrahmanalbatta-webdev/Batta-Website/actions/workflows/deploy-vercel.yml/dispatches'
            && $request->hasHeader('Authorization', 'Bearer github_pat_test')
            && $request['ref'] === 'main');
    }

    public function test_a_refused_token_is_logged_not_retried(): void
    {
        app(PlatformSettings::class)->update(['site_rebuild_token' => 'expired']);
        Http::fake(['api.github.com/*' => Http::response(['message' => 'Bad credentials'], 401)]);

        app()->call([new RebuildSite, 'handle']);

        Http::assertSentCount(1);
    }
}
