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
];
