<?php

namespace App\Console\Commands;

use App\Enums\ArticleStatus;
use App\Models\Article;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('articles:publish-scheduled')]
#[Description('Publish scheduled articles whose publish time has come')]
class PublishScheduledArticles extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $published = 0;

        Article::query()->dueForPublishing()->each(function (Article $article) use (&$published): void {
            $article->status = ArticleStatus::Published;
            // an article that was live before and got rescheduled keeps its first publish date (and isn't emailed again)
            $article->published_at ??= $article->publish_at;
            $article->save();
            $published++;
        });

        $this->info("Published {$published} article(s).");

        return self::SUCCESS;
    }
}
