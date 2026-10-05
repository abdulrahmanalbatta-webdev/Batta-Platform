<?php

namespace App\Support;

use Illuminate\Support\Facades\URL;

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

    /**
     * A signed link (e.g. unsubscribe) on APP_URL. The signature covers the path and query only, so the route
     * checks it with the "signed:relative" middleware.
     *
     * @param  array<string, mixed>  $parameters
     */
    public static function signedRoute(string $name, array $parameters = []): string
    {
        return rtrim((string) config('app.url'), '/').URL::signedRoute($name, $parameters, absolute: false);
    }
}
