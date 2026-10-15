<?php

namespace Tests\Feature;

use App\Notifications\Concerns\RetriesDelivery;
use App\Notifications\NewDeviceLogin;
use Illuminate\Contracts\Queue\ShouldQueue;
use Symfony\Component\Finder\Finder;
use Tests\TestCase;

class NotificationRetriesTest extends TestCase
{
    public function test_every_queued_notification_retries_a_failed_email(): void
    {
        $classes = collect(Finder::create()->files()->in(app_path('Notifications'))->name('*.php'))
            ->map(fn ($file): string => 'App\\Notifications\\'.str_replace(['/', '.php'], ['\\', ''], $file->getRelativePathname()))
            ->filter(fn (string $class): bool => class_exists($class) && is_subclass_of($class, ShouldQueue::class));

        $this->assertNotEmpty($classes);
        $classes->each(fn (string $class) => $this->assertContains(RetriesDelivery::class, class_uses_recursive($class), "{$class} gives up after one try"));
    }

    public function test_a_failed_email_is_tried_again_after_one_then_five_minutes(): void
    {
        $notification = new NewDeviceLogin('Chrome on Windows', '203.0.113.7', now());

        $this->assertSame(3, $notification->tries);
        $this->assertSame([60, 300], $notification->backoff());
    }
}
