<?php

namespace Tests\Feature\Console;

use App\Enums\ArticleStatus;
use App\Models\Article;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublishScheduledArticlesTest extends TestCase
{
    use RefreshDatabase;

    public function test_publishes_articles_whose_time_has_come(): void
    {
        $this->freezeSecond();
        $due = Article::factory()->scheduled(now()->subMinute())->create();
        $later = Article::factory()->scheduled(now()->addHour())->create();
        $draft = Article::factory()->create();

        $this->artisan('articles:publish-scheduled')->assertSuccessful();

        $this->assertSame(ArticleStatus::Published, $due->fresh()->status);
        $this->assertEquals(now()->subMinute(), $due->fresh()->published_at);
        $this->assertSame(ArticleStatus::Scheduled, $later->fresh()->status);
        $this->assertSame(ArticleStatus::Draft, $draft->fresh()->status);
    }

    public function test_command_runs_every_minute(): void
    {
        $this->artisan('schedule:list')->expectsOutputToContain('articles:publish-scheduled')->assertSuccessful();
    }
}
