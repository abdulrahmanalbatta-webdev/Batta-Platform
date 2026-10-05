<?php

namespace App\Notifications;

use App\Models\User;
use App\Support\AppUrl;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Carbon;

/**
 * "Was this you?" after a sign-in from a browser or network the member hasn't used before.
 */
class NewDeviceLogin extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public string $device, public string $ip, public Carbon $at) {}

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
        return (new MailMessage)
            ->subject('تسجيل دخول من جهاز جديد — '.config('app.name'))
            ->greeting('مرحباً '.$notifiable->name.'،')
            ->line('تم تسجيل الدخول إلى حسابك في لوحة التحكم من جهاز جديد:')
            ->line('الجهاز: '.$this->device)
            ->line('عنوان IP: '.$this->ip)
            ->line('الوقت: '.$this->at->toDateTimeString().' (UTC)')
            ->line('إذا كان هذا أنت فلا داعي لأي إجراء. وإن لم يكن، غيّر كلمة المرور فوراً وأنهِ الجلسات الأخرى من الإعدادات ← الأمان.')
            ->action('الأمان والأجهزة', AppUrl::route('settings.index', hash: 'security'));
    }
}
