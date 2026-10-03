<?php

namespace App\Notifications;

use App\Models\Student;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * An email written in the dashboard to one or many students; "{الاسم}" becomes each student's name.
 * Queued, so a message to every student doesn't hold up the request.
 */
class StudentMessage extends Notification implements ShouldQueue
{
    use Queueable;

    public const NAME_PLACEHOLDER = '{الاسم}';

    public function __construct(public string $subject, public string $body) {}

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
        $message = (new MailMessage)->subject($this->personalise($this->subject, $notifiable));

        foreach (preg_split('/\R{2,}/u', trim($this->personalise($this->body, $notifiable))) as $paragraph) {
            $message->line($paragraph);
        }

        return $message;
    }

    private function personalise(string $text, Student $student): string
    {
        return str_replace(self::NAME_PLACEHOLDER, $student->name, $text);
    }
}
