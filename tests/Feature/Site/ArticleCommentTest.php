<?php

namespace Tests\Feature\Site;

use App\Enums\Role;
use App\Models\Article;
use App\Models\ArticleComment;
use App\Models\Student;
use App\Models\User;
use App\Notifications\Alerts\CommentSubmitted;
use App\Support\PlatformSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class ArticleCommentTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, string>
     */
    private function signIn(Student $student): array
    {
        return ['Authorization' => 'Bearer '.$student->createToken('web')->plainTextToken];
    }

    public function test_a_student_comments_and_it_waits_for_moderation(): void
    {
        Notification::fake();
        $support = User::factory()->role(Role::Support)->create();
        $article = Article::factory()->published()->create(['slug' => 'vue-tips']);
        $student = Student::factory()->create(['name' => 'منى خالد']);

        $this->postJson(route('site.articles.comments.store', 'vue-tips'), ['body' => 'مقال مفيد جداً، شكراً'], $this->signIn($student))
            ->assertCreated()
            ->assertJsonPath('message', 'وصل تعليقك، وسيظهر بعد مراجعته.');

        $this->assertDatabaseHas('article_comments', ['article_id' => $article->id, 'student_id' => $student->id, 'status' => 'pending']);
        $this->getJson(route('site.articles.comments.index', 'vue-tips'))->assertOk()->assertJsonCount(0, 'data');
        Notification::assertSentTo($support, CommentSubmitted::class);
    }

    public function test_published_comments_show_oldest_first_with_first_name_and_reply_only(): void
    {
        $article = Article::factory()->published()->create(['slug' => 'vue-tips']);
        $first = ArticleComment::factory()->published()->for($article)->for(Student::factory()->state(['name' => 'منى خالد', 'email' => 'mona@example.com']))
            ->create(['created_at' => now()->subDay(), 'reply' => 'شكراً لكِ']);
        ArticleComment::factory()->published()->for($article)->create();
        ArticleComment::factory()->for($article)->create();
        ArticleComment::factory()->hidden()->for($article)->create();

        $response = $this->getJson(route('site.articles.comments.index', 'vue-tips'))
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.id', $first->id)
            ->assertJsonPath('data.0.name', 'منى')
            ->assertJsonPath('data.0.reply', 'شكراً لكِ');

        $this->assertStringNotContainsString('mona@example.com', $response->getContent());
    }

    public function test_guests_cannot_comment_and_bodies_are_validated(): void
    {
        Article::factory()->published()->create(['slug' => 'vue-tips']);

        $this->postJson(route('site.articles.comments.store', 'vue-tips'), ['body' => 'تعليق من زائر'])->assertUnauthorized();
        $this->postJson(route('site.articles.comments.store', 'vue-tips'), ['body' => 'أ'], $this->signIn(Student::factory()->create()))
            ->assertJsonValidationErrors('body');
    }

    public function test_drafts_cannot_be_commented_on_and_comments_can_be_switched_off(): void
    {
        Article::factory()->create(['slug' => 'draft']);
        Article::factory()->published()->create(['slug' => 'vue-tips']);
        $headers = $this->signIn(Student::factory()->create());

        $this->postJson(route('site.articles.comments.store', 'draft'), ['body' => 'تعليق على مسودة'], $headers)->assertNotFound();
        $this->getJson(route('site.articles.comments.index', 'draft'))->assertNotFound();

        app(PlatformSettings::class)->update(['article_comments' => false]);
        $this->postJson(route('site.articles.comments.store', 'vue-tips'), ['body' => 'التعليقات مغلقة'], $headers)
            ->assertForbidden();
        $this->assertDatabaseCount('article_comments', 0);
    }
}
