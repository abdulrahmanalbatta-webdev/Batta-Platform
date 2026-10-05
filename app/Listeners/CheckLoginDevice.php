<?php

namespace App\Listeners;

use App\Models\User;
use App\Notifications\NewDeviceLogin;
use App\Support\PlatformSettings;
use App\Support\UserAgent;
use Illuminate\Auth\Events\Login;

class CheckLoginDevice
{
    /**
     * How many devices a member's list remembers.
     */
    private const REMEMBERED = 10;

    /**
     * Remember the browser and network a member signs in from, and email them when it's one they haven't
     * used before (settings → الأمان → تنبيه الدخول من جهاز جديد). Their very first sign-in only records it.
     */
    public function handle(Login $event): void
    {
        if (! $event->user instanceof User) {
            return;
        }

        $user = $event->user;
        $device = (new UserAgent((string) request()->userAgent()))->label();
        $fingerprint = hash('sha256', $device.'|'.$this->network((string) request()->ip()));
        $known = $user->known_devices ?? [];

        if (in_array($fingerprint, $known, true)) {
            return;
        }

        $user->forceFill(['known_devices' => array_slice([$fingerprint, ...$known], 0, self::REMEMBERED)])->save();

        if ($known !== [] && app(PlatformSettings::class)->get('new_device_alert')) {
            $user->notify(new NewDeviceLogin($device, (string) request()->ip(), now()));
        }
    }

    /**
     * The network part of the address, so a changing address at home doesn't count as a new device.
     */
    private function network(string $ip): string
    {
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            return implode(':', array_slice(explode(':', $ip), 0, 4));
        }

        return implode('.', array_slice(explode('.', $ip), 0, 3));
    }
}
