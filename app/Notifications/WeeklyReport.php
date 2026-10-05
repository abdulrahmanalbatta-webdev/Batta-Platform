<?php

namespace App\Notifications;

use App\Models\User;
use App\Support\PlatformSettings;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * The Sunday summary for the owner and admins: last week's sales against the week before, and what's waiting.
 */
class WeeklyReport extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * @param  array<string, mixed>  $report  SalesReport::build(7)
     * @param  array{messages: int, reviews: int, leads: int}  $waiting  NavCounts::all()
     */
    public function __construct(public array $report, public array $waiting) {}

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(User $notifiable): MailMessage
    {
        $settings = app(PlatformSettings::class);
        $kpis = $this->report['kpis'];
        $change = fn (array $kpi): string => $kpi['change'] === null ? '' : ' ('.($kpi['change'] >= 0 ? '+' : '').$kpi['change'].'% عن الأسبوع السابق)';

        $mail = (new MailMessage)
            ->subject('التقرير الأسبوعي — '.config('app.name'))
            ->greeting('مرحباً '.$notifiable->name.'،')
            ->line('هذا ملخص آخر 7 أيام:')
            ->line('الإيرادات: '.$settings->money($kpis['revenue']['value']).$change($kpis['revenue']))
            ->line('الطلبات المكتملة: '.$kpis['orders']['value'].$change($kpis['orders']))
            ->line('طلاب جدد: '.$kpis['students']['value'].$change($kpis['students']));

        if ($best = $this->report['top_products'][0] ?? null) {
            $mail->line("الأكثر مبيعاً: {$best['name']} ({$best['orders']} طلب)");
        }

        return $mail
            ->line("بانتظارك: {$this->waiting['messages']} رسالة غير مقروءة، {$this->waiting['reviews']} تقييم للمراجعة، {$this->waiting['leads']} طلب مشروع جديد.")
            ->action('فتح التحليلات', route('analytics'))
            ->line('تقدر توقف هذا التقرير من الإعدادات ← الإشعارات.');
    }
}
