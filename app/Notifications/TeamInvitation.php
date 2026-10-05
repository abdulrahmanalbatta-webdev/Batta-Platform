<?php

namespace App\Notifications;

use App\Models\User;
use App\Support\AppUrl;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TeamInvitation extends Notification
{
    use Queueable;

    /**
     * @param  string  $token  A token from the "invitations" password broker.
     */
    public function __construct(public string $token, public User $invitedBy) {}

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
        $url = AppUrl::route('password.reset', [
            'token' => $this->token,
            'email' => $notifiable->getEmailForPasswordReset(),
            'invite' => 1,
        ]);

        return (new MailMessage)
            ->subject('دعوة للانضمام إلى فريق '.config('app.name'))
            ->greeting("مرحباً {$notifiable->name}!")
            ->line("دعاك {$this->invitedBy->name} للانضمام إلى لوحة تحكم ".config('app.name')." بصلاحية: {$notifiable->role->label()}.")
            ->action('قبول الدعوة وتعيين كلمة المرور', $url)
            ->line('ينتهي هذا الرابط بعد '.(config('auth.passwords.invitations.expire') / 60 / 24).' أيام.')
            ->line('إذا لم تكن تتوقع هذه الدعوة، تجاهل هذه الرسالة.');
    }
}
