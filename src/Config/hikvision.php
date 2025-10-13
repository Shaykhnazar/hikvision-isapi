<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Default Hikvision Device Configuration
    |--------------------------------------------------------------------------
    |
    | Specify which device should be used by default when not explicitly
    | specified. This should match one of the device keys below.
    |
    */
    'default' => env('HIKVISION_DEFAULT_DEVICE', 'primary'),

    /*
    |--------------------------------------------------------------------------
    | Hikvision Devices
    |--------------------------------------------------------------------------
    |
    | Configure multiple Hikvision devices here. Each device should have
    | its own configuration with IP, credentials, and connection settings.
    |
    | You can add as many devices as needed for your application.
    | Example: 'entrance', 'exit', 'canteen', 'office', etc.
    |
    */
    'devices' => [
        'primary' => [
            'ip' => env('HIKVISION_IP', '192.168.1.100'),
            'port' => env('HIKVISION_PORT', 80),
            'username' => env('HIKVISION_USERNAME', 'admin'),
            'password' => env('HIKVISION_PASSWORD'),
            'protocol' => env('HIKVISION_PROTOCOL', 'http'), // http or https
            'timeout' => env('HIKVISION_TIMEOUT', 30),
            'verify_ssl' => env('HIKVISION_VERIFY_SSL', false),
        ],

        // Example: Additional device configuration
        // Uncomment and configure for multiple devices
        /*
        'entrance' => [
            'ip' => env('HIKVISION_ENTRANCE_IP', '192.168.1.101'),
            'port' => env('HIKVISION_ENTRANCE_PORT', 80),
            'username' => env('HIKVISION_ENTRANCE_USERNAME', 'admin'),
            'password' => env('HIKVISION_ENTRANCE_PASSWORD'),
            'protocol' => env('HIKVISION_ENTRANCE_PROTOCOL', 'http'),
            'timeout' => env('HIKVISION_ENTRANCE_TIMEOUT', 30),
            'verify_ssl' => env('HIKVISION_ENTRANCE_VERIFY_SSL', false),
        ],

        'exit' => [
            'ip' => env('HIKVISION_EXIT_IP', '192.168.1.102'),
            'port' => env('HIKVISION_EXIT_PORT', 80),
            'username' => env('HIKVISION_EXIT_USERNAME', 'admin'),
            'password' => env('HIKVISION_EXIT_PASSWORD'),
            'protocol' => env('HIKVISION_EXIT_PROTOCOL', 'http'),
            'timeout' => env('HIKVISION_EXIT_TIMEOUT', 30),
            'verify_ssl' => env('HIKVISION_EXIT_VERIFY_SSL', false),
        ],

        'canteen' => [
            'ip' => env('HIKVISION_CANTEEN_IP', '192.168.1.103'),
            'port' => env('HIKVISION_CANTEEN_PORT', 80),
            'username' => env('HIKVISION_CANTEEN_USERNAME', 'admin'),
            'password' => env('HIKVISION_CANTEEN_PASSWORD'),
            'protocol' => env('HIKVISION_CANTEEN_PROTOCOL', 'http'),
            'timeout' => env('HIKVISION_CANTEEN_TIMEOUT', 30),
            'verify_ssl' => env('HIKVISION_CANTEEN_VERIFY_SSL', false),
        ],
        */
    ],

    /*
    |--------------------------------------------------------------------------
    | Response Format
    |--------------------------------------------------------------------------
    */
    'format' => env('HIKVISION_FORMAT', 'json'), // json or xml

    /*
    |--------------------------------------------------------------------------
    | Logging
    |--------------------------------------------------------------------------
    */
    'logging' => [
        'enabled' => env('HIKVISION_LOGGING', true),
        'channel' => env('HIKVISION_LOG_CHANNEL', 'stack'),
    ],
];
