<?php

namespace Tests\Feature;

use App\Enums\ArticleStatus;
use App\Enums\Role;
use App\Models\Article;
use App\Models\Student;
use App\Models\Subscriber;
use App\Models\User;
use App\Notifications\NewArticle;
use App\Support\AppUrl;
use App\Support\PlatformSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class NewsletterTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_site_form_subscribes_and_resubscribes(): void
    {
        $this->postJson(route('site.newsletter'), ['email' => 'Reader@Example.com'])->assertCreated();
        $this->postJson(route('site.newsletter'), ['email' => 'reader@example.com'])->assertCreated();

        $subscriber = Subscriber::query()->sole();
        $this->assertSame('reader@example.com', $subscriber->email);

        $subscriber->update(['unsubscribed_at' => now()]);
        $this->postJson(route('site.newsletter'), ['email' => 'reader@example.com'])->assertCreated();
        $this->assertTrue($subscriber->fresh()->isActive());

        $this->postJson(route('site.newsletter'), ['email' => 'not-an-email'])->assertJsonValidationErrors('email');
    }

    public function test_the_bot_trap_keeps_nothing(): void
    {
        $this->postJson(route('site.newsletter'), ['email' => 'bot@example.com', 'website' => 'http://spam.test'])->assertCreated();

        $this->assertSame(0, Subscriber::query()->count());
    }

    public function test_a_new_article_reaches_students_and_subscribers_once_each(): void
    {
        Notification::fake();
        app(PlatformSettings::class)->update(['newsletter_new_articles' => true]);
        $student = Student::factory()->create(['email' => 'student@example.com']);
        $alsoSubscribed = Subscriber::factory()->create(['email' => 'student@example.com']);
        $reader = Subscriber::factory()->create(['email' => 'reader@example.com']);
        $gone = Subscriber::factory()->unsubscribed()->create();
        $leftStudent = Student::factory()->create(['email' => 'left@example.com']);
        Subscriber::factory()->unsubscribed()->create(['email' => 'left@example.com']);

        Article::factory()->create(['status' => ArticleStatus::Published, 'send_newsletter' => true]);

        Notification::assertSentTo($student, NewArticle::class);
        Notification::assertSentTo($reader, NewArticle::class);
        Notification::assertNotSentTo($alsoSubscribed, NewArticle::class);
        Notification::assertNotSentTo($gone, NewArticle::class);
        Notification::assertNotSentTo($leftStudent, NewArticle::class);
    }

    public function test_the_email_links_to_a_signed_unsubscribe_page(): void
    {
        $reader = Subscriber::factory()->create(['email' => 'reader@example.com']);
        $mail = (new NewArticle(Article::factory()->published()->create()))->toMail($reader);

        $link = collect($mail->outroLines)->first(fn (string $line): bool => str_contains($line, '/newsletter/unsubscribe'));
        $url = substr($link, strpos($link, 'http'));
        $this->assertStringStartsWith(rtrim(config('app.url'), '/').'/newsletter/unsubscribe?', $url);

        $this->get($url)->assertOk()->assertSee('ألغِ اشتراكي');
        $this->assertTrue($reader->fresh()->isActive());

        $this->post($url)->assertOk()->assertSee('تم إلغاء اشتراكك');
        $this->assertFalse($reader->fresh()->isActive());
    }

    public function test_an_unsigned_or_tampered_link_does_nothing(): void
    {
        $reader = Subscriber::factory()->create(['email' => 'reader@example.com']);
        $url = AppUrl::signedRoute('newsletter.unsubscribe', ['email' => 'someone@example.com']);

        $this->get('/newsletter/unsubscribe?email=reader@example.com')->assertForbidden();
        $this->post(str_replace('someone%40example.com', 'reader%40example.com', $url))->assertForbidden();

        $this->assertTrue($reader->fresh()->isActive());
    }

    public function test_members_see_the_list_and_student_managers_remove_from_it(): void
    {
        Student::factory()->create(['email' => 'student@example.com']);
        $subscriber = Subscriber::factory()->create(['email' => 'student@example.com']);
        Subscriber::factory()->unsubscribed()->create();

        $this->actingAs(User::factory()->role(Role::Editor)->create())->getJson(route('api.subscribers.index'))
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.1.is_student', true);

        $this->actingAs(User::factory()->role(Role::Editor)->create())->deleteJson(route('api.subscribers.destroy', $subscriber))->assertForbidden();
        $this->actingAs(User::factory()->role(Role::Support)->create())->deleteJson(route('api.subscribers.destroy', $subscriber))->assertNoContent();
        $this->assertModelMissing($subscriber);
    }
}
