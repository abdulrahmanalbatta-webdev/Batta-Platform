<?php

namespace App\Notifications\Alerts;

use App\Enums\AlertType;
use App\Models\User;
use App\Support\AppUrl;
use App\Support\PlatformSettings;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * A bell notification for the team, also emailed to members who keep that email switched on.
 * The bell entry is written straight away; only the email waits for the queue.
 */
abstract class TeamAlert extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Skip the alert if its subject is deleted before the queue sends the email.
     */
    public bool $deleteWhenMissingModels = true;

    abstract public function type(): AlertType;

    abstract protected function title(): string;

    abstract protected function meta(): string;

    /**
     * The dashboard page to open, as a key of the routes in layouts/partials/app-config (App.url on the page).
     */
    abstract protected function page(): string;

    /**
     * Query parameters for that page, e.g. the conversation to open.
     *
     * @return array<string, int|string>
     */
    protected function params(): array
    {
        return [];
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(User $notifiable): array
    {
        return $notifiable->wantsEmailFor($this->type()) ? ['database', 'mail'] : ['database'];
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
     * @return array{type: string, title: string, meta: string, page: string, params: array<string, int|string>}
     */
    public function toArray(User $notifiable): array
    {
        return [
            'type' => $this->type()->value,
            'title' => $this->title(),
            'meta' => $this->meta(),
            'page' => $this->page(),
            'params' => $this->params(),
        ];
    }

    public function toMail(User $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject($this->title())
            ->greeting('مرحباً '.$notifiable->name.'،')
            ->line($this->title())
            ->line($this->meta())
            ->action('فتح لوحة التحكم', $this->url())
            ->line('تقدر توقف هذه الرسائل من الإعدادات ← الإشعارات.');
    }

    /**
     * An amount in the platform currency, kept left-to-right inside the Arabic text so the sign stays after the number.
     */
    protected function money(float $amount): string
    {
        return "\u{2066}".app(PlatformSettings::class)->money($amount)."\u{2069}";
    }

    private function url(): string
    {
        $routes = ['orders' => 'orders.index', 'leads' => 'leads.index', 'reviews' => 'reviews.index', 'messages' => 'messages.index'];

        return AppUrl::route($routes[$this->page()], $this->params());
    }
}
