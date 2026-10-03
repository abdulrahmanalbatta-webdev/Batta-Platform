<?php

namespace App\Notifications;

use App\Models\Student;
use App\Models\Workshop;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Reminds a registered student when and where a workshop takes place.
 */
class WorkshopReminder extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Workshop $workshop) {}

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
    public function toMail(Student $notifiable): MailMessage
    {
        $workshop = $this->workshop;

        return (new MailMessage)
            ->subject("تذكير: {$workshop->title}")
            ->greeting("مرحباً {$notifiable->name}!")
            ->line("نذكّرك بورشة \"{$workshop->title}\" التي حجزت مقعدك فيها.")
            ->line('الموعد: '.$workshop->date->toDateString().' الساعة '.substr($workshop->start_time, 0, 5))
            ->line("المكان: {$workshop->format->label()} · {$workshop->place}")
            ->line('نراك هناك!');
    }
}
