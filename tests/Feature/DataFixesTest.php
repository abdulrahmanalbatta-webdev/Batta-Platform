<?php

namespace Tests\Feature;

use App\Models\Article;
use App\Models\Order;
use App\Models\Setting;
use App\Models\Student;
use App\Models\User;
use App\Notifications\NewArticle;
use App\Support\PlatformSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class DataFixesTest extends TestCase
{
    use RefreshDatabase;

    public function test_rescheduling_a_published_article_keeps_its_date_and_does_not_email_again(): void
    {
        Notification::fake();
        app(PlatformSettings::class)->update(['newsletter_new_articles' => true]);
        Student::factory()->create();
        $article = Article::factory()->create(['send_newsletter' => true]);
        $article->update(['status' => 'published']);
        $firstPublished = $article->fresh()->published_at;

        $article->update(['status' => 'scheduled', 'publish_at' => now()->addHour()]);
        $this->travel(2)->hours();
        $this->artisan('articles:publish-scheduled')->assertSuccessful();

        $this->assertSame('published', $article->fresh()->status->value);
        $this->assertTrue($article->fresh()->published_at->equalTo($firstPublished));
        Notification::assertSentTimes(NewArticle::class, 1);
    }

    public function test_clearing_the_smtp_server_goes_back_to_the_env_mailer(): void
    {
        $envMailer = config('mail.default');
        $settings = app(PlatformSettings::class);

        $settings->update(['mail_host' => 'smtp.example.com', 'mail_from_address' => 'no-reply@batta.dev']);
        $this->assertSame('smtp', config('mail.default'));

        $settings->update(['mail_host' => null, 'mail_from_address' => null]);
        $this->assertSame($envMailer, config('mail.default'));
        $this->assertNotSame('no-reply@batta.dev', config('mail.from.address'));
    }

    public function test_a_secret_from_an_old_app_key_reads_as_unset_instead_of_crashing(): void
    {
        Setting::query()->create(['key' => 'mail_password', 'value' => 'not-decryptable-under-this-key']);
        $settings = app(PlatformSettings::class);
        $settings->forget();

        $this->assertNull($settings->get('mail_password'));
        $this->actingAs(User::factory()->create())->get(route('dashboard'))->assertOk();
    }

    public function test_the_year_is_compared_with_the_same_stretch_a_year_before(): void
    {
        $this->travelTo(now()->setDate(2026, 10, 5)->setTime(12, 0));
        // same revenue on the same dates a year apart: no change
        Order::factory()->create(['total' => 100, 'paid_at' => now()->subDays(3)]);
        Order::factory()->create(['total' => 100, 'paid_at' => now()->subYear()->subDays(3)]);
        // after "today" a year ago: outside the comparison
        Order::factory()->create(['total' => 500, 'paid_at' => now()->subYear()->addDays(10)]);

        $response = $this->actingAs(User::factory()->create())->getJson(route('api.analytics', ['days' => 365]));

        $response->assertJsonPath('data.kpis.revenue.previous', 100)->assertJsonPath('data.kpis.revenue.change', 0);
    }
}
