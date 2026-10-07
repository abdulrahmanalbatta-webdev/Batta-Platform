<?php

namespace Tests\Feature\Api;

use App\Enums\Role;
use App\Models\Article;
use App\Models\Comment;
use App\Models\Course;
use App\Models\Student;
use App\Models\User;
use App\Models\Workshop;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CommentTest extends TestCase
{
    use RefreshDatabase;

    public function test_lists_comments_with_student_article_and_reply(): void
    {
        $owner = User::factory()->create(['name' => 'عبد الرحمن']);
        Comment::factory()->create(['created_at' => now()->subDay()]);
        $comment = Comment::factory()->published()->create(['reply' => 'شكراً', 'replied_by' => $owner->id]);

        $this->actingAs(User::factory()->role(Role::Editor)->create())->getJson(route('api.comments.index'))
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.name', $comment->student->name)
            ->assertJsonPath('data.0.type', 'article')
            ->assertJsonPath('data.0.type_label', 'مقال')
            ->assertJsonPath('data.0.target', $comment->commentable->title)
            ->assertJsonPath('data.0.target_key', $comment->commentable->slug)
            ->assertJsonPath('data.0.status_label', 'منشور')
            ->assertJsonPath('data.0.replied_by', 'عبد الرحمن');
    }

    public function test_support_publishes_hides_replies_and_deletes(): void
    {
        $comment = Comment::factory()->create();
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

    public function test_the_articles_list_counts_published_comments_and_the_sidebar_pending_ones(): void
    {
        $owner = User::factory()->create();
        $article = Article::factory()->published()->create();
        Comment::factory()->published()->count(2)->for($article, 'commentable')->create();
        Comment::factory()->for($article, 'commentable')->create();

        $this->actingAs($owner)->getJson(route('api.articles.index'))->assertJsonPath('data.0.comments', 2);
        $this->actingAs($owner)->get(route('comments.index'))->assertOk()->assertSee('"comments":1', false);
    }

    public function test_course_and_workshop_comments_and_replies_show_where_they_were_left(): void
    {
        $course = Course::factory()->create(['title' => 'دورة Vue', 'slug' => 'vue']);
        $workshop = Workshop::factory()->create(['title' => 'ورشة Git']);
        $question = Comment::factory()->published()->for($course, 'commentable')->for(Student::factory()->state(['name' => 'منى خالد']))
            ->create(['body' => 'هل الدورة للمبتدئين؟', 'created_at' => now()->subDays(2)]);
        Comment::factory()->replyTo($question)->create(['created_at' => now()->subDay()]);
        Comment::factory()->for($workshop, 'commentable')->create();

        $this->actingAs(User::factory()->role(Role::Support)->create())->getJson(route('api.comments.index'))
            ->assertOk()
            ->assertJsonPath('data.0.type', 'workshop')
            ->assertJsonPath('data.0.type_label', 'ورشة')
            ->assertJsonPath('data.0.target', 'ورشة Git')
            ->assertJsonPath('data.0.target_key', $workshop->id)
            ->assertJsonPath('data.1.type', 'course')
            ->assertJsonPath('data.1.target_key', 'vue')
            ->assertJsonPath('data.1.parent_id', $question->id)
            ->assertJsonPath('data.1.parent.name', 'منى خالد')
            ->assertJsonPath('data.1.parent.body', 'هل الدورة للمبتدئين؟')
            ->assertJsonPath('data.2.parent', null);
    }

    public function test_deleting_a_comment_deletes_the_replies_under_it(): void
    {
        $comment = Comment::factory()->published()->create();
        $reply = Comment::factory()->replyTo($comment)->create();

        $this->actingAs(User::factory()->role(Role::Support)->create())->deleteJson(route('api.comments.destroy', $comment))->assertNoContent();

        $this->assertModelMissing($reply);
    }
}
