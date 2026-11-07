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
];
