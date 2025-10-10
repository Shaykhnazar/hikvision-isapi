<?php

declare(strict_types=1);

namespace Shaykhnazar\HikvisionIsapi\Tests;

use Orchestra\Testbench\TestCase as Orchestra;
use Shaykhnazar\HikvisionIsapi\HikvisionIsapiServiceProvider;

abstract class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();
    }

    protected function getPackageProviders($app): array
    {
        return [
            HikvisionIsapiServiceProvider::class,
        ];
    }

    protected function getEnvironmentSetUp($app): void
    {
        config()->set('hikvision.default', 'primary');
        config()->set('hikvision.devices.primary', [
            'ip' => env('HIKVISION_IP', '192.168.1.100'),
            'port' => env('HIKVISION_PORT', 80),
            'username' => env('HIKVISION_USERNAME', 'admin'),
            'password' => env('HIKVISION_PASSWORD', 'password'),
            'protocol' => env('HIKVISION_PROTOCOL', 'http'),
            'timeout' => env('HIKVISION_TIMEOUT', 30),
            'verify_ssl' => env('HIKVISION_VERIFY_SSL', false),
        ]);
        config()->set('hikvision.format', 'json');
        config()->set('hikvision.logging.enabled', true);
        config()->set('hikvision.logging.channel', 'stack');
    }
}
