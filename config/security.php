<?php

return [

    /*
    | Send the Content-Security-Policy header (App\Http\Middleware\SecurityHeaders). On everywhere but the
    | local environment, where Laravel Boost injects inline scripts of its own.
    */

    'csp' => (bool) env('SECURITY_CSP', env('APP_ENV') !== 'local'),

];
