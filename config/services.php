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

    'bulksmsbd' => [
        'base_url' => env('BULKSMSBD_BASE_URL', 'https://bulksmsbd.net'),
        'api_key' => env('BULKSMSBD_API_KEY'),
        'sender_id' => env('BULKSMSBD_SENDER_ID'),
    ],

    'otp' => [
        // log = write codes to the log (development); bulksmsbd = real SMS.
        'driver' => env('OTP_DRIVER', 'log'),
        'ttl_minutes' => (int) env('OTP_TTL_MINUTES', 5),
        'length' => (int) env('OTP_LENGTH', 6),
        // Local development only: any code of the right length is accepted.
        // Ignored unless APP_ENV=local (see OtpService::anyCodeAccepted()).
        'any_code' => (bool) env('OTP_ANY_CODE', false),
    ],

    'ai' => [
        // none = AI features hidden. anthropic = Claude (needs ANTHROPIC_API_KEY).
        'driver' => env('AI_DRIVER', 'none'),
        'anthropic_key' => env('ANTHROPIC_API_KEY'),
        'model' => env('AI_MODEL', 'claude-haiku-5-5'),
        'timeout' => (int) env('AI_TIMEOUT', 30),
    ],

    // F37: Web Push (free phone notifications). Keys are written to .env by `dawat:webpush-keys` on deploy.
    'webpush' => [
        'public_key' => env('WEBPUSH_PUBLIC_KEY'),
        'private_key' => env('WEBPUSH_PRIVATE_KEY'),
        'subject' => env('WEBPUSH_SUBJECT', env('APP_URL', 'https://dawat.cloud')),
    ],

    // F54: Facebook ad tracking. Pixel in the browser + Conversions API from the server.
    // Off unless META_PIXEL_ID is set; the server half also needs META_CAPI_TOKEN.
    'meta' => [
        'pixel_id' => env('META_PIXEL_ID', '4537200536607649'), // দাওয়াত ওয়েবসাইট dataset (public id, not a secret)
        'capi_token' => env('META_CAPI_TOKEN'),
        // Only while checking events in Events Manager → Test events; leave empty in normal use.
        'test_event_code' => env('META_TEST_EVENT_CODE'),
        'graph_version' => env('META_GRAPH_VERSION', 'v23.0'),
    ],
];
