<?php

namespace App\Notifications;

use App\Models\User;
use App\Support\AppUrl;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Number;

/**
 * Tells the owner their data export finished: in the bell straight away, and by email.
 */
class ExportReady extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public string $file, public int $bytes) {}

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    /**
     * The bell entry is stored right away; the email goes through the default queue.
     *
     * @return array<string, string>
     */
    public function viaConnections(): array
    {
        return ['database' => 'sync'];
    }

    /**
     * @return array{type: string, title: string, meta: string, page: string, params: array<string, string>, hash: string}
     */
    public function toArray(User $notifiable): array
    {
        return [
            'type' => 'export',
            'title' => 'نسخة البيانات جاهزة للتنزيل',
            'meta' => $this->file.' · '.Number::fileSize($this->bytes),
            'page' => 'settings',
            'params' => [],
            'hash' => 'security',
        ];
    }

    public function toMail(User $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('نسخة البيانات جاهزة — '.config('app.name'))
            ->greeting('مرحباً '.$notifiable->name.'،')
            ->line('انتهى تجهيز نسخة من بيانات المنصة ('.Number::fileSize($this->bytes).').')
            ->line('نزّلها من لوحة التحكم، وتُحذف تلقائياً بعد 7 أيام.')
            ->action('الإعدادات ← الأمان', AppUrl::route('settings.index', hash: 'security'));
    }
}
