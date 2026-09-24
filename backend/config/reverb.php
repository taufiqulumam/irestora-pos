<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Reverb Server Configuration
    |--------------------------------------------------------------------------
    |
    | This configuration file is used by the Reverb WebSocket server when
    | running via `php artisan reverb:start`. It controls the server's
    | network settings, authentication, and other options.
    |
    */

    'host' => env('REVERB_HOST', '0.0.0.0'),

    'port' => env('REVERB_PORT', 8080),

    'scheme' => env('REVERB_SCHEME', 'http'),

    'app' => [
        'id' => env('REVERB_APP_ID', 'pos-app'),
        'key' => env('REVERB_APP_KEY', 'pos-key'),
        'secret' => env('REVERB_APP_SECRET', 'pos-secret'),
    ],

    'ssl' => [
        'enabled' => env('REVERB_SSL_ENABLED', false),
        'cert_path' => env('REVERB_SSL_CERT_PATH'),
        'key_path' => env('REVERB_SSL_KEY_PATH'),
        'passphrase' => env('REVERB_SSL_PASSPHRASE'),
    ],

    'allowed_origins' => env('REVERB_ALLOWED_ORIGINS', '*'),

    'max_request_size' => env('REVERB_MAX_REQUEST_SIZE', 1000000),

    'stats' => [
        'enabled' => env('REVERB_STATS_ENABLED', true),
        'interval' => env('REVERB_STATS_INTERVAL', 60),
    ],

    'debug' => env('REVERB_DEBUG', false),

    'cors' => [
        'enabled' => env('REVERB_CORS_ENABLED', true),
        'allowed_origins' => explode(',', env('REVERB_CORS_ALLOWED_ORIGINS', '*')),
        'allowed_methods' => ['GET', 'POST'],
        'allowed_headers' => ['Content-Type', 'Authorization', 'X-Requested-With'],
        'max_age' => 86400,
    ],

    'throttle' => [
        'enabled' => env('REVERB_THROTTLE_ENABLED', true),
        'max_requests' => env('REVERB_THROTTLE_MAX_REQUESTS', 100),
        'decay_minutes' => env('REVERB_THROTTLE_DECAY_MINUTES', 1),
    ],

];