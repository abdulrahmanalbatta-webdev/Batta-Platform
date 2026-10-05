<?php

namespace App\Observers;

use App\Enums\ArticleStatus;
use App\Models\Article;
use App\Models\Student;
use App\Notifications\NewArticle;
use App\Support\PlatformSettings;
use Illuminate\Contracts\Events\ShouldHandleEventsAfterCommit;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Notification;

class ArticleObserver implements ShouldHandleEventsAfterCommit
{
    public function created(Article $article): void
    {
        $this->announce($article);
    }

    /**
     * published_at is only ever filled once, the first time the article goes live.
     */
    public function updated(Article $article): void
    {
        if ($article->wasChanged('published_at')) {
            $this->announce($article);
        }
    }

    /**
     * Email a newly published article to every student who isn't suspended, when both the platform switch
     * (settings → الإشعارات) and the article's own "send to subscribers" box are on. One queued email per student.
     */
    private function announce(Article $article): void
    {
        if ($article->status !== ArticleStatus::Published || $article->published_at === null || ! $article->send_newsletter) {
            return;
        }

        if (! app(PlatformSettings::class)->get('newsletter_new_articles')) {
            return;
        }

        Student::query()->whereNull('suspended_at')->chunkById(500, fn (Collection $students) => Notification::send($students, new NewArticle($article)));
    }
}
