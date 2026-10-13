<?php

namespace App\Console\Commands;

use App\Enums\Role;
use App\Models\User;
use App\Support\GoogleAnalytics;
use App\Support\PlatformSettings;
use App\Support\SocialAccounts;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Whether the live platform has what it needs to work: the scheduled tasks running, mail connected, the site
 * address set… Run by the "server check" workflow (no SSH login needed) or by hand. Prints no secrets: the repository
 * is public, so its Actions logs are too.
 */
#[Signature('app:health')]
#[Description('Check that the scheduler, the queue worker, mail and the main settings are in place')]
class CheckHealth extends Command
{
    /**
     * The scheduler writes this every minute (routes/console.php); an older one means its cron isn't running.
     */
    public const HEARTBEAT = 'scheduler.heartbeat';

    private int $problems = 0;

    public function handle(PlatformSettings $settings, SocialAccounts $social, GoogleAnalytics $analytics): int
    {
        $heartbeat = Cache::get(self::HEARTBEAT);
        $this->check(
            $heartbeat !== null && Carbon::parse($heartbeat)->gt(now()->subMinutes(5)),
            'المهام المجدولة (schedule:run) شغالة'.($heartbeat ? ' — آخر تشغيل '.Carbon::parse($heartbeat)->diffForHumans() : ''),
            'المهمة المجدولة "php artisan schedule:run" لا تعمل: أضفها في alwaysdata ← Scheduled tasks كل دقيقة.',
        );

        $oldest = DB::table('jobs')->min('available_at');
        $waiting = DB::table('jobs')->count();
        $this->check(
            $oldest === null || $oldest > now()->subMinutes(5)->getTimestamp(),
            "منفّذ المهام (queue:work) شغّال — {$waiting} بالانتظار",
            "{$waiting} مهمة عالقة منذ أكثر من 5 دقائق (إشعارات وإيميلات): أضف \"php artisan queue:work --stop-when-empty --max-time=50\" في Scheduled tasks كل دقيقة.",
        );

        $failed = DB::table('failed_jobs')->count();
        $this->check($failed === 0, 'لا مهام فاشلة', "{$failed} مهمة فشلت، آخرها:");
        // what failed and the exception's class, never its message (it can carry addresses or server details)
        DB::table('failed_jobs')->latest('failed_at')->limit(3)->get()->each(function (object $job): void {
            $name = json_decode($job->payload, true)['displayName'] ?? '?';
            $this->line("      {$job->failed_at}  ".class_basename($name).' ← '.Str::before(Str::before($job->exception, ':'), "\n"));
        });

        $mailer = config('mail.default');
        $this->check(
            $mailer === 'smtp' && filled(config('mail.mailers.smtp.password')),
            'البريد مربوط (SMTP)',
            $mailer === 'smtp' ? 'خادم SMTP محفوظ بدون كلمة مرور: أدخلها في الإعدادات ← البريد.' : 'البريد غير مربوط، الرسائل لا تُرسل: املأ الإعدادات ← البريد.',
        );

        $siteUrl = (string) $settings->get('site_url');
        $this->check(
            $siteUrl !== PlatformSettings::definitions()['site_url']['default'],
            "رابط الموقع: {$siteUrl}",
            "رابط الموقع ما زال الافتراضي ({$siteUrl}): روابط استعادة كلمة المرور لن تعمل. عدّله من الإعدادات ← عام.",
        );

        $this->check(User::query()->where('role', Role::Owner)->exists(), 'حساب المالك موجود', 'لا يوجد حساب مالك: php artisan app:create-owner');

        $this->line('');
        $this->line('اختياري:');
        $this->line('  الدخول بـ Google: '.($social->enabled('google') ? 'مفعّل' : 'غير مضبوط'));
        $this->line('  الدخول بـ Facebook: '.($social->enabled('facebook') ? 'مفعّل' : 'غير مضبوط'));
        $this->line('  تحديث الموقع فوراً بعد النشر: '.(filled($settings->get('site_rebuild_token')) ? 'مفعّل' : 'غير مضبوط (كل 6 ساعات)'));
        $this->line('  Google Analytics: '.(filled($settings->get('ga_measurement_id')) ? 'الموقع يرسل الزيارات' : 'غير مضبوط').($analytics->configured() ? '، واللوحة تقرأ التقارير' : ''));

        $log = storage_path('logs/laravel-'.now()->format('Y-m-d').'.log');
        $errors = is_file($log) ? substr_count((string) file_get_contents($log), '.ERROR:') : 0;
        $this->line("  أخطاء في سجل اليوم: {$errors}");

        $this->line('');
        $this->problems === 0 ? $this->info('كل شيء جاهز.') : $this->warn("{$this->problems} مشكلة تحتاج إصلاحاً.");

        return $this->problems === 0 ? self::SUCCESS : self::FAILURE;
    }

    private function check(bool $passes, string $ok, string $problem): void
    {
        if ($passes) {
            $this->line("  ✓ {$ok}");

            return;
        }

        $this->problems++;
        $this->line("  ✗ {$problem}");
    }
}
