<?php

declare(strict_types=1);

namespace Shaykhnazar\HikvisionIsapi\Facades;

use Illuminate\Support\Facades\Facade;
use Shaykhnazar\HikvisionIsapi\Client\DeviceManager;
use Shaykhnazar\HikvisionIsapi\Client\HikvisionClient;

/**
 * Hikvision Facade for multi-device access
 *
 * @method static HikvisionClient device(?string $deviceName = null) Get client for specific device
 * @method static HikvisionClient default() Get client for default device
 * @method static array availableDevices() Get all available device names
 * @method static bool hasDevice(string $deviceName) Check if device exists
 * @method static void clearClients() Clear cached clients
 *
 * @see DeviceManager
 */
class Hikvision extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return DeviceManager::class;
    }
}
