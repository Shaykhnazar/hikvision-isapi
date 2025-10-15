<?php

declare(strict_types=1);

namespace Shaykhnazar\HikvisionIsapi\Client;

use Shaykhnazar\HikvisionIsapi\Authentication\Contracts\AuthenticatorInterface;
use Shaykhnazar\HikvisionIsapi\Client\Contracts\DeviceProviderInterface;
use Shaykhnazar\HikvisionIsapi\Client\Contracts\HttpClientInterface;
use Shaykhnazar\HikvisionIsapi\Client\Providers\ConfigDeviceProvider;
use Shaykhnazar\HikvisionIsapi\Exceptions\HikvisionException;

/**
 * Device Manager for handling multiple Hikvision devices
 *
 * Supports loading devices from multiple sources:
 * - Config files (default)
 * - Database (DatabaseDeviceProvider)
 * - Custom callbacks (CallbackDeviceProvider)
 * - Any source via DeviceProviderInterface
 */
class DeviceManager
{
    private array $clients = [];
    private DeviceProviderInterface $provider;

    /**
     * @param HttpClientInterface $httpClient
     * @param AuthenticatorInterface $authenticator
     * @param DeviceProviderInterface|array|null $provider Device provider or legacy config array
     */
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly AuthenticatorInterface $authenticator,
        DeviceProviderInterface|array|null $provider = null
    ) {
        // Support legacy array config for backward compatibility
        if (is_array($provider) || $provider === null) {
            $this->provider = new ConfigDeviceProvider($provider ?? []);
        } else {
            $this->provider = $provider;
        }
    }

    /**
     * Get client for specific device
     */
    public function device(?string $deviceName = null): HikvisionClient
    {
        $deviceName = $deviceName ?? $this->provider->getDefaultDevice();

        if (!isset($this->clients[$deviceName])) {
            $this->clients[$deviceName] = $this->createClient($deviceName);
        }

        return $this->clients[$deviceName];
    }

    /**
     * Get client for default device
     */
    public function default(): HikvisionClient
    {
        return $this->device();
    }

    /**
     * Get all available device names
     */
    public function availableDevices(): array
    {
        return $this->provider->getDeviceNames();
    }

    /**
     * Check if device exists in configuration
     */
    public function hasDevice(string $deviceName): bool
    {
        return $this->provider->hasDevice($deviceName);
    }

    /**
     * Set custom device provider at runtime
     *
     * Useful for switching between config and database providers dynamically
     */
    public function setProvider(DeviceProviderInterface $provider): void
    {
        $this->provider = $provider;
        $this->clearClients(); // Clear cached clients when provider changes
    }

    /**
     * Get current device provider
     */
    public function getProvider(): DeviceProviderInterface
    {
        return $this->provider;
    }

    /**
     * Register device at runtime (for dynamic device management)
     *
     * Note: This only works with in-memory registration and will be lost on next request.
     * For persistent devices, use DatabaseDeviceProvider.
     *
     * @param string $deviceName
     * @param array $config Device configuration
     */
    public function registerDevice(string $deviceName, array $config): void
    {
        // If using ConfigDeviceProvider, we can't modify it (immutable)
        // So we create a new client directly
        if ($this->provider instanceof ConfigDeviceProvider) {
            // Create temporary config for this device
            $deviceConfig = array_merge(
                $this->provider->getGlobalConfig(),
                ['devices' => [$deviceName => $config], 'default' => $deviceName]
            );

            $this->clients[$deviceName] = new HikvisionClient(
                $this->httpClient,
                $this->authenticator,
                $deviceConfig
            );
        }
    }

    /**
     * Create client for specific device
     */
    private function createClient(string $deviceName): HikvisionClient
    {
        if (!$this->hasDevice($deviceName)) {
            throw new HikvisionException("Device '{$deviceName}' not found in configuration");
        }

        // Get device config from provider
        $deviceConfig = $this->provider->getDeviceConfig($deviceName);

        if (!$deviceConfig) {
            throw new HikvisionException("Configuration for device '{$deviceName}' is invalid or missing");
        }

        // Merge with global config
        $globalConfig = $this->provider->getGlobalConfig();
        $fullConfig = array_merge($globalConfig, [
            'devices' => [$deviceName => $deviceConfig],
            'default' => $deviceName,
        ]);

        return new HikvisionClient(
            $this->httpClient,
            $this->authenticator,
            $fullConfig
        );
    }

    /**
     * Clear cached clients (useful for testing or when devices change)
     */
    public function clearClients(): void
    {
        $this->clients = [];
    }

    /**
     * Reload devices from provider (useful with DatabaseDeviceProvider)
     *
     * Clears cache and forces provider to reload devices on next access
     */
    public function reload(): void
    {
        $this->clearClients();

        // If provider supports cache clearing, do it
        if (method_exists($this->provider, 'clearCache')) {
            $this->provider->clearCache();
        }
    }

    /**
     * Alias for reload() - clear cache
     */
    public function clearCache(): void
    {
        $this->reload();
    }

    /**
     * Switch to a specific device and return its client
     *
     * This is a convenience method that combines hasDevice check and device retrieval
     *
     * @param string $deviceName
     * @return HikvisionClient
     * @throws HikvisionException if device not found
     */
    public function switchDevice(string $deviceName): HikvisionClient
    {
        if (!$this->hasDevice($deviceName)) {
            throw new HikvisionException("Cannot switch to device '{$deviceName}': device not found");
        }

        return $this->device($deviceName);
    }
}
