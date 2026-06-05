<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Named rate-limiter caps (ultrareview P0)
    |--------------------------------------------------------------------------
    |
    | Per-minute caps for the `auth` and `public-lookup` limiters registered in
    | AppServiceProvider. Defaults are the production-secure values; only
    | docker-compose.e2e.yml raises them via env so the e2e suite — which logs
    | in as the same test user many times from one CI IP — isn't throttled.
    | (env() belongs in config files; the provider reads these via config().)
    |
    */

    'auth_per_email' => (int) env('THROTTLE_AUTH_PER_EMAIL', 5),

    'auth_per_ip' => (int) env('THROTTLE_AUTH_PER_IP', 20),

    'public_lookup_per_ip' => (int) env('THROTTLE_PUBLIC_LOOKUP', 30),

];
