<?php

return [
    /*
    | A kitchen's public location is its home moved this many metres in a
    | random direction, chosen once per home. All buyer-facing distances use
    | it, so nobody can find the real house by querying from many points.
    */
    'location_fuzz' => [
        'min_m' => (int) env('LOCATION_FUZZ_MIN_M', 120),
        'max_m' => (int) env('LOCATION_FUZZ_MAX_M', 250),
    ],

    /*
    | OTP SMS cost money. Besides the per-phone limits in OtpService:
    | per IP per day, and for the whole app per hour (a sudden flood stops
    | instead of draining the SMS balance).
    */
    'otp' => [
        'per_ip_per_day' => (int) env('OTP_PER_IP_PER_DAY', 30),
        'global_per_hour' => (int) env('OTP_GLOBAL_PER_HOUR', 300),
    ],

    /*
    | Behind Cloudflare or a load balancer, list its addresses so rate limits
    | see the visitor's real IP. Empty = trust no proxy (nginx faces the internet).
    */
    'trusted_proxies' => array_values(array_filter(array_map('trim', explode(',', (string) env('TRUSTED_PROXIES', ''))))),

    /*
    | F19 share cards: a Bangla PNG/JPEG made with headless Chrome. Puppeteer
    | lives outside the app (setup-server.sh installs it), so `npm ci` stays small.
    */
    'share_cards' => [
        'enabled' => (bool) env('SHARE_CARDS_ENABLED', false),
        'chrome_path' => env('SHARE_CARD_CHROME_PATH', '/usr/bin/google-chrome'),
        'node_binary' => env('SHARE_CARD_NODE_BINARY', 'node'),
        'node_modules' => env('SHARE_CARD_NODE_MODULES'),
        'width' => 1080,
        'height' => 1350,
    ],

    /*
    | F26 regular orders: SMS each customer when today's box is booked (with
    | the delivery code) or when their cook is not cooking. ~৳0.35 per SMS.
    */
    'orders' => [
        'sms' => (bool) env('ORDER_SMS_ENABLED', true),
        'max_pause_days' => 60,
    ],
];
