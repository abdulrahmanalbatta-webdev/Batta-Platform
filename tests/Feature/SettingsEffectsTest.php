<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\User;
use App\Notifications\NewDeviceLogin;
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

        $this->actingAs(User::factory()->create())->get(route('leads.index'))
            ->assertSee('"currency_symbol":'.json_encode(' ر.س'), escape: false)
            ->assertSee('الميزانية (ر.س)', escape: false);
    }

    public function test_weekly_report_goes_to_owner_and_admins_unless_switched_off(): void
    {
        Notification::fake();
        $owner = User::factory()->owner()->create();
        $admin = User::factory()->admin()->create();
        $editor = User::factory()->role(Role::Editor)->create();
        Enrollment::factory()->for(Course::factory()->create(['title' => 'Next.js']))->create();

        $this->artisan('reports:weekly')->assertSuccessful();

        Notification::assertSentTo([$owner, $admin], WeeklyReport::class, function (WeeklyReport $report) use ($owner): bool {
            $lines = $report->toMail($owner)->introLines;

            return $report->report['kpis']['enrollments']['value'] === 1.0
                && in_array('التسجيلات في الدورات: 1', $lines, true)
                && in_array('التسجيلات في الورش: 0', $lines, true)
                && in_array('طلاب جدد: 1', $lines, true)
                && in_array('الأكثر تسجيلاً: Next.js (1 تسجيل)', $lines, true);
        });
        Notification::assertNotSentTo($editor, WeeklyReport::class);

        $this->settings(['weekly_report' => false]);
        $this->artisan('reports:weekly')->expectsOutput('The weekly report is switched off.');
        Notification::assertSentToTimes($owner, WeeklyReport::class, 1);
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
