<?php

declare(strict_types=1);

namespace Shaykhnazar\HikvisionIsapi\Client\Contracts;

/**
 * Interface for device configuration providers
 *
 * Allows loading device configurations from different sources:
 * - Config files (ConfigDeviceProvider)
 * - Database (DatabaseDeviceProvider)
 * - Custom callbacks (CallbackDeviceProvider)
 * - API endpoints, Redis, etc.
 */
interface DeviceProviderInterface
{
    /**
     * Get all available device names
     *
     * @return array<string>
     */
    public function getDeviceNames(): array;

    /**
     * Get configuration for specific device
     *
     * @param string $deviceName
     * @return array{
     *     ip: string,
     *     port: int,
     *     username: string,
     *     password: string,
     *     protocol: string,
     *     timeout: int,
     *     verify_ssl: bool
     * }|null
     */
    public function getDeviceConfig(string $deviceName): ?array;

    /**
     * Get default device name
     *
     * @return string
     */
    public function getDefaultDevice(): string;

    /**
     * Check if device exists
     *
     * @param string $deviceName
     * @return bool
     */
    public function hasDevice(string $deviceName): bool;

    /**
     * Get global configuration (format, logging, etc.)
     *
     * @return array
     */
    public function getGlobalConfig(): array;
}
