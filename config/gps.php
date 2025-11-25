<?php

return [
    /*
    |--------------------------------------------------------------------------
    | GPS Location TTL (Time To Live)
    |--------------------------------------------------------------------------
    |
    | Duration in seconds to keep location data in Redis before auto-expiration.
    | Users inactive beyond this time are removed from the active users set.
    |
    */
    'location_ttl' => env('GPS_LOCATION_TTL', 3600), // 1 hour

    /*
    |--------------------------------------------------------------------------
    | Redis to MySQL Sync Interval
    |--------------------------------------------------------------------------
    |
    | How often (in seconds) to sync location data from Redis to MySQL.
    | Lower values = more frequent persistence, higher DB load.
    |
    */
    'sync_interval' => env('GPS_SYNC_INTERVAL', 60), // 1 minute

    /*
    |--------------------------------------------------------------------------
    | Maximum Batch Size for Sync
    |--------------------------------------------------------------------------
    |
    | Maximum number of location records to sync from Redis to MySQL in
    | a single batch operation. Helps prevent memory issues.
    |
    */
    'max_batch_size' => env('GPS_MAX_BATCH_SIZE', 1000),

    /*
    |--------------------------------------------------------------------------
    | Rate Limiting
    |--------------------------------------------------------------------------
    |
    | Maximum number of location updates allowed per user per time window.
    |
    */
    'rate_limit' => [
        'max_attempts' => 120, // Max updates per window
        'decay_seconds' => 60, // Time window (1 minute)
    ],

    /*
    |--------------------------------------------------------------------------
    | Broadcasting
    |--------------------------------------------------------------------------
    |
    | Enable/disable real-time broadcasting of location updates.
    |
    */
    'broadcast_enabled' => env('GPS_BROADCAST_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | Location Validation
    |--------------------------------------------------------------------------
    |
    | Validation rules for GPS coordinates.
    |
    */
    'validation' => [
        'latitude' => [
            'min' => -90,
            'max' => 90,
        ],
        'longitude' => [
            'min' => -180,
            'max' => 180,
        ],
        'accuracy' => [
            'max' => 10000, // meters
        ],
    ],
];
