<?php

namespace App\Notifications;

use App\Support\AppUrl;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent to a member's previous address after they change the email they sign in with.
 */
class EmailChanged extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public string $name, public string $newEmail) {}

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
        return (new MailMessage)
            ->subject('تم تغيير بريد حسابك — '.config('app.name'))
            ->greeting('مرحباً '.$this->name.'،')
            ->line('تم تغيير البريد الذي تسجّل به الدخول إلى لوحة التحكم إلى: '.$this->newEmail)
            ->line('إذا لم تقم بذلك، تواصل مع مالك المنصة فوراً.')
            ->action('لوحة التحكم', AppUrl::route('login'));
    }
}
