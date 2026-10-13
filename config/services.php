<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Resend, Postmark, AWS, and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    // the public site is rebuilt (its GitHub Actions deploy workflow) after content changes, so search
    // engines and AI crawlers get the new pages at once; the token is a dashboard setting (site_rebuild_token)
    'site_deploy' => [
        'repository' => env('SITE_REPOSITORY', 'abdulrahmanalbatta-webdev/Batta-Website'),
        'workflow' => env('SITE_DEPLOY_WORKFLOW', 'deploy-vercel.yml'),
        'ref' => env('SITE_DEPLOY_REF', 'main'),
    ],

];
