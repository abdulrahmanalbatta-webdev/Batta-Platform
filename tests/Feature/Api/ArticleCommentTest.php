<?php

namespace Tests\Feature\Api;

use App\Enums\Role;
use App\Models\Article;
use App\Models\ArticleComment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ArticleCommentTest extends TestCase
{
    use RefreshDatabase;

    public function test_lists_comments_with_student_article_and_reply(): void
    {
        $owner = User::factory()->create(['name' => 'عبد الرحمن']);
        ArticleComment::factory()->create(['created_at' => now()->subDay()]);
        $comment = ArticleComment::factory()->published()->create(['reply' => 'شكراً', 'replied_by' => $owner->id]);

        $this->actingAs(User::factory()->role(Role::Accountant)->create())->getJson(route('api.comments.index'))
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.name', $comment->student->name)
            ->assertJsonPath('data.0.article', $comment->article->title)
            ->assertJsonPath('data.0.article_slug', $comment->article->slug)
            ->assertJsonPath('data.0.status_label', 'منشور')
            ->assertJsonPath('data.0.replied_by', 'عبد الرحمن');
    }

    public function test_support_publishes_hides_replies_and_deletes(): void
    {
        $comment = ArticleComment::factory()->create();
        $support = User::factory()->role(Role::Support)->create(['name' => 'يوسف']);

        $this->actingAs($support)->putJson(route('api.comments.status.update', $comment), ['status' => 'published'])
            ->assertOk()->assertJsonPath('data.status', 'published');
        $this->actingAs($support)->putJson(route('api.comments.status.update', $comment), ['status' => 'pending'])
            ->assertJsonValidationErrors('status');

        $this->actingAs($support)->putJson(route('api.comments.reply.update', $comment), ['reply' => 'سعيد أنه أفادك'])
            ->assertOk()->assertJsonPath('data.reply', 'سعيد أنه أفادك')->assertJsonPath('data.replied_by', 'يوسف');
        $this->actingAs($support)->deleteJson(route('api.comments.reply.destroy', $comment))
            ->assertOk()->assertJsonPath('data.reply', null);

        $this->actingAs($support)->deleteJson(route('api.comments.destroy', $comment))->assertNoContent();
        $this->assertModelMissing($comment);
    }

    public function test_accountant_cannot_moderate(): void
    {
        $comment = ArticleComment::factory()->create();
        $accountant = User::factory()->role(Role::Accountant)->create();

        $this->actingAs($accountant)->putJson(route('api.comments.status.update', $comment), ['status' => 'published'])->assertForbidden();
        $this->actingAs($accountant)->deleteJson(route('api.comments.destroy', $comment))->assertForbidden();
    }

    public function test_the_articles_list_counts_published_comments_and_the_sidebar_pending_ones(): void
    {
        $owner = User::factory()->create();
        $article = Article::factory()->published()->create();
        ArticleComment::factory()->published()->count(2)->for($article)->create();
        ArticleComment::factory()->for($article)->create();

        $this->actingAs($owner)->getJson(route('api.articles.index'))->assertJsonPath('data.0.comments', 2);
        $this->actingAs($owner)->get(route('comments.index'))->assertOk()->assertSee('"comments":1', false);
    }
}
