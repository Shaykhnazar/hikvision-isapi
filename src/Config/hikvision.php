<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Default Hikvision Device Configuration
    |--------------------------------------------------------------------------
    */
    'default' => env('HIKVISION_DEFAULT_DEVICE', 'primary'),

    /*
    |--------------------------------------------------------------------------
    | Hikvision Devices
    |--------------------------------------------------------------------------
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
