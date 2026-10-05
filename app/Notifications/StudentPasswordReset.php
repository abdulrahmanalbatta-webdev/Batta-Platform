<?php

namespace App\Notifications;

use App\Support\PlatformSettings;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * A student's "forgot password" link. It opens the public site's reset page (site_url/reset-password),
 * which sends the token and email back to POST /api/v1/auth/reset-password.
 */
class StudentPasswordReset extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public string $token) {}

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
    public function toMail(object $notifiable): MailMessage
    {
        $url = rtrim((string) app(PlatformSettings::class)->get('site_url'), '/').'/reset-password?'.http_build_query([
            'token' => $this->token,
            'email' => $notifiable->email,
        ]);

        return (new MailMessage)
            ->subject('إعادة تعيين كلمة المرور — '.config('app.name'))
            ->greeting('مرحباً '.$notifiable->name.'،')
            ->line('وصلنا طلب لإعادة تعيين كلمة مرور حسابك. الرابط صالح لمدة '.config('auth.passwords.students.expire').' دقيقة.')
            ->action('تعيين كلمة مرور جديدة', $url)
            ->line('إذا لم تطلب ذلك، تجاهل هذه الرسالة وستبقى كلمة مرورك كما هي.');
    }
}
