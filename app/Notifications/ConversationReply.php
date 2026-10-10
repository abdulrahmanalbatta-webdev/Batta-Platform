<?php

namespace App\Notifications;

use App\Models\ConversationMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Attachment;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * A team reply from the messages page, emailed to the conversation's contact with its attachment.
 */
class ConversationReply extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Skip the email if the message is deleted before the queue sends it.
     */
    public bool $deleteWhenMissingModels = true;

    public function __construct(public ConversationMessage $message) {}

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
        $mail = (new MailMessage)
            ->subject('رد جديد من '.config('app.name'))
            ->greeting('مرحباً '.$this->message->conversation->name.'،');

        foreach (preg_split('/\R{2,}/u', trim((string) $this->message->body)) as $paragraph) {
            if ($paragraph !== '') {
                $mail->line($paragraph);
            }
        }

        if ($this->message->attachment_path !== null) {
            $mail->line('أرفقنا لك ملف: '.$this->message->attachment_name)
                ->attach(Attachment::fromStorageDisk(ConversationMessage::attachmentDisk(), $this->message->attachment_path)->as($this->message->attachment_name));
        }

        return $mail;
    }
}
