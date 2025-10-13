<?php

declare(strict_types=1);

namespace Shaykhnazar\HikvisionIsapi\Client\Providers;

use Shaykhnazar\HikvisionIsapi\Client\Contracts\DeviceProviderInterface;

/**
 * Config-based device provider
 *
 * Loads device configurations from Laravel config files.
 * This is the default provider for backward compatibility.
 */
class ConfigDeviceProvider implements DeviceProviderInterface
{
    public function __construct(
        private readonly array $config
    ) {}

    public function getDeviceNames(): array
    {
        return array_keys($this->config['devices'] ?? []);
    }

    public function getDeviceConfig(string $deviceName): ?array
    {
        return $this->config['devices'][$deviceName] ?? null;
    }

    public function getDefaultDevice(): string
    {
        return $this->config['default'] ?? 'primary';
    }

    public function hasDevice(string $deviceName): bool
    {
        return isset($this->config['devices'][$deviceName]);
    }

    public function getGlobalConfig(): array
    {
        return [
            'format' => $this->config['format'] ?? 'json',
            'logging' => $this->config['logging'] ?? [
                'enabled' => true,
                'channel' => 'stack',
            ],
        ];
    }
}
