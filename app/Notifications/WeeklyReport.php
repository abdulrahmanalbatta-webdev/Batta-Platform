<?php

namespace App\Notifications;

use App\Models\User;
use App\Support\AppUrl;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * The Sunday summary for the owner and admins: last week's registrations against the week before, and what's waiting.
 */
class WeeklyReport extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * @param  array<string, mixed>  $report  RegistrationsReport::build(7)
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
        $kpis = $this->report['kpis'];
        $change = fn (array $kpi): string => $kpi['change'] === null ? '' : ' ('.($kpi['change'] >= 0 ? '+' : '').$kpi['change'].'% عن الأسبوع السابق)';

        $mail = (new MailMessage)
            ->subject('التقرير الأسبوعي — '.config('app.name'))
            ->greeting('مرحباً '.$notifiable->name.'،')
            ->line('هذا ملخص آخر 7 أيام:')
            ->line('التسجيلات في الدورات: '.$kpis['enrollments']['value'].$change($kpis['enrollments']))
            ->line('التسجيلات في الورش: '.$kpis['workshop_registrations']['value'].$change($kpis['workshop_registrations']))
            ->line('طلاب جدد: '.$kpis['students']['value'].$change($kpis['students']));

        if ($top = $this->report['top_items'][0] ?? null) {
            $mail->line("الأكثر تسجيلاً: {$top['name']} ({$top['registrations']} تسجيل)");
        }

        return $mail
            ->line("بانتظارك: {$this->waiting['messages']} رسالة غير مقروءة، {$this->waiting['reviews']} تقييم للمراجعة، {$this->waiting['leads']} طلب مشروع جديد.")
            ->action('فتح التحليلات', AppUrl::route('analytics'))
            ->line('تقدر توقف هذا التقرير من الإعدادات ← الإشعارات.');
    }
}
