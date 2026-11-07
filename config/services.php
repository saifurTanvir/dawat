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

];
