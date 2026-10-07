<?php

namespace Tests\Feature\Site;

use App\Enums\Role;
use App\Models\Article;
use App\Models\Comment;
use App\Models\Course;
use App\Models\Student;
use App\Models\User;
use App\Models\Workshop;
use App\Notifications\Alerts\CommentSubmitted;
use App\Support\PlatformSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class CommentTest extends TestCase
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

        $this->assertDatabaseHas('comments', ['commentable_type' => Article::class, 'commentable_id' => $article->id, 'parent_id' => null, 'student_id' => $student->id, 'status' => 'pending']);
        $this->getJson(route('site.articles.comments.index', 'vue-tips'))->assertOk()->assertJsonCount(0, 'data');
        Notification::assertSentTo($support, CommentSubmitted::class);
    }

    public function test_published_comments_show_oldest_first_with_first_name_and_reply_only(): void
    {
        $article = Article::factory()->published()->create(['slug' => 'vue-tips']);
        $first = Comment::factory()->published()->for($article, 'commentable')->for(Student::factory()->state(['name' => 'منى خالد', 'email' => 'mona@example.com']))
            ->create(['created_at' => now()->subDay(), 'reply' => 'شكراً لكِ']);
        Comment::factory()->published()->for($article, 'commentable')->create();
        Comment::factory()->for($article, 'commentable')->create();
        Comment::factory()->hidden()->for($article, 'commentable')->create();

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
        $this->assertDatabaseCount('comments', 0);
    }

    public function test_students_reply_to_a_published_comment_and_replies_show_under_it(): void
    {
        Notification::fake();
        $article = Article::factory()->published()->create(['slug' => 'vue-tips']);
        $question = Comment::factory()->published()->for($article, 'commentable')->create(['reply' => 'سؤال جميل']);
        $student = Student::factory()->create(['name' => 'سامي علي']);

        $this->postJson(route('site.articles.comments.store', 'vue-tips'), ['body' => 'جرّبتها وتعمل تماماً', 'parent_id' => $question->id], $this->signIn($student))
            ->assertCreated()
            ->assertJsonPath('message', 'وصل ردّك، وسيظهر بعد مراجعته.');
        $reply = Comment::query()->where('parent_id', $question->id)->sole();

        // waits for moderation like any comment
        $this->getJson(route('site.articles.comments.index', 'vue-tips'))->assertJsonCount(0, 'data.0.replies');

        $reply->update(['status' => 'published']);
        Comment::factory()->replyTo($question)->hidden()->create();

        $this->getJson(route('site.articles.comments.index', 'vue-tips'))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.reply', 'سؤال جميل')
            ->assertJsonCount(1, 'data.0.replies')
            ->assertJsonPath('data.0.replies.0.name', 'سامي')
            ->assertJsonPath('data.0.replies.0.body', 'جرّبتها وتعمل تماماً');
    }

    public function test_a_reply_must_answer_a_published_top_level_comment_on_the_same_page(): void
    {
        $article = Article::factory()->published()->create(['slug' => 'vue-tips']);
        $other = Article::factory()->published()->create(['slug' => 'other']);
        $pending = Comment::factory()->for($article, 'commentable')->create();
        $elsewhere = Comment::factory()->published()->for($other, 'commentable')->create();
        $reply = Comment::factory()->published()->replyTo(Comment::factory()->published()->for($article, 'commentable')->create())->create();
        $headers = $this->signIn(Student::factory()->create());

        foreach ([$pending, $elsewhere, $reply] as $parent) {
            $this->postJson(route('site.articles.comments.store', 'vue-tips'), ['body' => 'رد غير مسموح', 'parent_id' => $parent->id], $headers)
                ->assertJsonValidationErrors('parent_id');
        }
    }

    public function test_students_comment_on_published_courses_and_on_workshops(): void
    {
        Notification::fake();
        $course = Course::factory()->create(['slug' => 'vue', 'status' => 'published']);
        Course::factory()->create(['slug' => 'draft-course', 'status' => 'draft']);
        $workshop = Workshop::factory()->create();
        $headers = $this->signIn(Student::factory()->create());

        $this->postJson(route('site.courses.comments.store', 'vue'), ['body' => 'هل الدورة مناسبة للمبتدئين؟'], $headers)->assertCreated();
        $this->postJson(route('site.workshops.comments.store', $workshop->id), ['body' => 'هل الورشة مسجّلة؟'], $headers)->assertCreated();
        $this->postJson(route('site.courses.comments.store', 'draft-course'), ['body' => 'على مسودة'], $headers)->assertNotFound();

        $this->assertDatabaseHas('comments', ['commentable_type' => Course::class, 'commentable_id' => $course->id]);
        $this->assertDatabaseHas('comments', ['commentable_type' => Workshop::class, 'commentable_id' => $workshop->id]);

        Comment::query()->update(['status' => 'published']);
        $this->getJson(route('site.courses.comments.index', 'vue'))->assertJsonPath('data.0.body', 'هل الدورة مناسبة للمبتدئين؟');
        $this->getJson(route('site.workshops.comments.index', $workshop->id))->assertJsonPath('data.0.body', 'هل الورشة مسجّلة؟');
        $this->getJson(route('site.workshops.show', $workshop))->assertOk()->assertJsonPath('data.id', $workshop->id);
    }
}
