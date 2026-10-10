<?php

namespace App\Support;

/**
 * Links that leave the app (emails) are built on APP_URL, never on the Host header of the request that
 * triggered them, so a forged Host can't turn a reset or invitation link into one to someone else's site.
 */
class AppUrl
{
    /**
     * @param  array<string, mixed>  $parameters
     */
    public static function route(string $name, array $parameters = [], string $hash = ''): string
    {
        return rtrim((string) config('app.url'), '/').route($name, $parameters, false).($hash !== '' ? '#'.$hash : '');
    }
}
