<?php

namespace Tests\Feature;

use App\Console\Commands\CheckHealth;
use App\Models\User;
use App\Support\PlatformSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CheckHealthCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_fresh_install_lists_what_is_missing(): void
    {
        DB::table('jobs')->insert(['queue' => 'default', 'payload' => '{}', 'attempts' => 0, 'available_at' => now()->subMinutes(10)->getTimestamp(), 'created_at' => now()->subMinutes(10)->getTimestamp()]);

        $this->artisan('app:health')
            ->expectsOutputToContain('schedule:run" لا تعمل')
            ->expectsOutputToContain('1 مهمة عالقة')
            ->expectsOutputToContain('البريد غير مربوط')
            ->expectsOutputToContain('رابط الموقع ما زال الافتراضي')
            ->expectsOutputToContain('لا يوجد حساب مالك')
            ->assertFailed();
    }

    public function test_failed_jobs_show_what_failed_but_not_the_error_message(): void
    {
        DB::table('failed_jobs')->insert([
            'uuid' => 'f1',
            'connection' => 'database',
            'queue' => 'default',
            'payload' => json_encode(['displayName' => 'App\\Notifications\\StudentPasswordReset']),
            'exception' => "Symfony\\Component\\Mailer\\Exception\\TransportException: 525 Unauthorized IP 203.0.113.7\n#0 trace",
            'failed_at' => now(),
        ]);

        $this->artisan('app:health')
            ->expectsOutputToContain('1 مهمة فشلت')
            ->expectsOutputToContain('StudentPasswordReset ← Symfony\\Component\\Mailer\\Exception\\TransportException')
            ->doesntExpectOutputToContain('203.0.113.7')
            ->assertFailed();
    }

    public function test_a_ready_platform_passes_without_printing_secrets(): void
    {
        User::factory()->owner()->create();
        Cache::forever(CheckHealth::HEARTBEAT, now()->subMinute()->toIso8601String());
        app(PlatformSettings::class)->update(['site_url' => 'https://batta-website.vercel.app', 'mail_host' => 'smtp.example.com', 'mail_username' => 'me', 'mail_password' => 'very-secret-pass']);

        $this->artisan('app:health')
            ->expectsOutputToContain('المهام المجدولة (schedule:run) شغالة')
            ->expectsOutputToContain('البريد مربوط')
            ->expectsOutputToContain('كل شيء جاهز.')
            ->doesntExpectOutputToContain('very-secret-pass')
            ->assertSuccessful();
    }
}
