<?php

namespace App\Notifications;

use App\Models\Article;
use App\Models\Student;
use App\Support\PlatformSettings;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * A new article, emailed to the students (settings → الإشعارات → إرسال المقالات الجديدة للطلاب).
 */
class NewArticle extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Skip the email if the article is deleted before the queue sends it.
     */
    public bool $deleteWhenMissingModels = true;

    public function __construct(public Article $article) {}

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
        $siteUrl = rtrim((string) app(PlatformSettings::class)->get('site_url'), '/');

        return (new MailMessage)
            ->subject('مقال جديد: '.$this->article->title)
            ->greeting('مرحباً '.$notifiable->name.'،')
            ->line('نشرنا مقالاً جديداً قد يهمك:')
            ->line($this->article->title)
            ->lineIf(filled($this->article->excerpt), (string) $this->article->excerpt)
            ->action('اقرأ المقال', $siteUrl.'/articles/'.$this->article->slug);
    }
}
