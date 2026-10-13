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

    public function test_todays_errors_show_where_they_happened_but_not_their_message(): void
    {
        $log = storage_path('logs/laravel-'.now()->format('Y-m-d').'.log');
        $base = base_path();
        file_put_contents($log, implode("\n", [
            '[2026-10-13 09:15:02] production.ERROR: SQLSTATE[HY000] secret-host.example {"exception":"[object] (Illuminate\\Database\\QueryException(code: 2002): SQLSTATE[HY000] secret-host.example at '.$base.'/vendor/laravel/framework/src/Connection.php:838)',
            '[stacktrace]',
            '#0 trace',
            '"} ',
            '[2026-10-13 10:00:00] production.INFO: all good',
            '[2026-10-13 11:30:45] production.ERROR: Something went wrong for user@example.com',
        ])."\n");

        try {
            $this->artisan('app:health')
                ->expectsOutputToContain('أخطاء في سجل اليوم: 2')
                ->expectsOutputToContain('09:15:02  Illuminate\\Database\\QueryException ← vendor/laravel/framework/src/Connection.php:838')
                ->expectsOutputToContain('11:30:45  خطأ بلا استثناء')
                ->doesntExpectOutputToContain('secret-host.example')
                ->doesntExpectOutputToContain('user@example.com');
        } finally {
            unlink($log);
        }
    }
}
