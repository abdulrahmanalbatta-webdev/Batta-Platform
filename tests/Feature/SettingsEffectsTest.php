<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\Role;
use App\Models\Article;
use App\Models\Order;
use App\Models\Student;
use App\Models\User;
use App\Notifications\NewArticle;
use App\Notifications\NewDeviceLogin;
use App\Notifications\OrderInvoice;
use App\Notifications\WeeklyReport;
use App\Support\PlatformSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class SettingsEffectsTest extends TestCase
{
    use RefreshDatabase;

    private function settings(array $values): void
    {
        app(PlatformSettings::class)->update($values);
    }

    public function test_dashboard_pages_use_the_platform_currency(): void
    {
        $this->settings(['currency' => 'SAR']);

        $this->actingAs(User::factory()->create())->get(route('courses.create'))
            ->assertSee('"currency_symbol":'.json_encode(' ر.س'), escape: false)
            ->assertSee('<span class="addon">ر.س</span>', escape: false);
    }

    public function test_invoice_shows_vat_note_currency_and_bank_details(): void
    {
        $this->settings([
            'currency' => 'JOD',
            'vat_percent' => 16,
            'invoice_note' => 'شكراً لك من فريق البطة.',
            'bank_transfer_instructions' => 'IBAN JO00 1234',
        ]);
        $order = Order::factory()->create(['total' => 116, 'subtotal' => 116, 'status' => OrderStatus::Pending, 'paid_at' => null, 'payment_method' => PaymentMethod::BankTransfer]);

        $lines = (new OrderInvoice($order))->toMail($order->student)->introLines;

        $this->assertContains('الإجمالي: 116 د.أ', $lines);
        $this->assertContains('منها ضريبة القيمة المضافة (16%): 16 د.أ', $lines);
        $this->assertContains('IBAN JO00 1234', $lines);
        $this->assertSame('شكراً لك من فريق البطة.', end($lines));
    }

    public function test_weekly_report_goes_to_owner_and_admins_unless_switched_off(): void
    {
        Notification::fake();
        $owner = User::factory()->owner()->create();
        $admin = User::factory()->admin()->create();
        $editor = User::factory()->role(Role::Editor)->create();
        Order::factory()->create(['total' => 79]);

        $this->artisan('reports:weekly')->assertSuccessful();

        Notification::assertSentTo([$owner, $admin], WeeklyReport::class, fn (WeeklyReport $report): bool => $report->report['kpis']['revenue']['value'] === 79.0);
        Notification::assertNotSentTo($editor, WeeklyReport::class);

        $this->settings(['weekly_report' => false]);
        $this->artisan('reports:weekly')->expectsOutput('The weekly report is switched off.');
        Notification::assertSentToTimes($owner, WeeklyReport::class, 1);
    }

    public function test_new_article_is_emailed_to_active_students_when_both_switches_are_on(): void
    {
        Notification::fake();
        $active = Student::factory()->create();
        $suspended = Student::factory()->suspended()->create();
        $article = Article::factory()->create(['send_newsletter' => true]);

        $article->update(['status' => 'published']);
        Notification::assertNothingSent();

        $this->settings(['newsletter_new_articles' => true]);
        $other = Article::factory()->create(['send_newsletter' => true]);
        $other->update(['status' => 'published']);
        // editing a live article doesn't send it again
        $other->update(['title' => 'عنوان جديد']);

        Notification::assertSentToTimes($active, NewArticle::class, 1);
        Notification::assertNotSentTo($suspended, NewArticle::class);
    }

    public function test_article_without_its_newsletter_box_is_not_emailed(): void
    {
        Notification::fake();
        $this->settings(['newsletter_new_articles' => true]);
        Student::factory()->create();

        Article::factory()->create(['send_newsletter' => false])->update(['status' => 'published']);

        Notification::assertNothingSent();
    }

    public function test_sign_in_from_a_new_device_is_emailed_after_the_first_one(): void
    {
        Notification::fake();
        $member = User::factory()->create(['email' => 'sara@batta.dev']);
        $signIn = function (string $agent, string $ip): void {
            $this->withServerVariables(['REMOTE_ADDR' => $ip])
                ->withHeader('User-Agent', $agent)
                ->postJson(route('login.store'), ['email' => 'sara@batta.dev', 'password' => 'password'])
                ->assertOk();
            // sign out again, as if from another browser
            $this->app['auth']->guard('web')->logout();
            $this->flushSession();
        };
        $chrome = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/128.0 Safari/537.36';
        $iphone = 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.0 Mobile/15E148 Safari/604.1';

        $signIn($chrome, '10.0.0.5');
        // same browser, same network: known
        $signIn($chrome, '10.0.0.77');
        Notification::assertNothingSent();

        $signIn($iphone, '10.0.0.5');

        Notification::assertSentTo($member, NewDeviceLogin::class, fn (NewDeviceLogin $alert): bool => $alert->device === 'iPhone · Safari');
        $this->assertCount(2, $member->fresh()->known_devices);
    }
}
