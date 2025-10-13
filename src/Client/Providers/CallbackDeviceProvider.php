<?php

declare(strict_types=1);

namespace Shaykhnazar\HikvisionIsapi\Client\Providers;

use Shaykhnazar\HikvisionIsapi\Client\Contracts\DeviceProviderInterface;

/**
 * Callback-based device provider
 *
 * Allows loading device configurations from custom callbacks.
 * Maximum flexibility - load from API, Redis, cache, or any custom source.
 *
 * Example usage:
 * ```php
 * $provider = new CallbackDeviceProvider(
 *     deviceNamesCallback: fn() => ['entrance', 'exit', 'canteen'],
 *     deviceConfigCallback: fn($name) => [
 *         'ip' => "192.168.1.{$name}",
 *         'username' => 'admin',
 *         'password' => env("TERMINAL_{$name}_PASSWORD"),
 *         // ...
 *     ],
 *     defaultDevice: 'entrance'
 * );
 * ```
 */
class CallbackDeviceProvider implements DeviceProviderInterface
{
    /**
     * @param callable $deviceNamesCallback Callback that returns array of device names
     * @param callable $deviceConfigCallback Callback that returns device config for given name
     * @param string $defaultDevice Default device name
     * @param callable|null $hasDeviceCallback Optional callback to check device existence
     */
    public function __construct(
        private $deviceNamesCallback,
        private $deviceConfigCallback,
        private readonly string $defaultDevice = 'primary',
        private $hasDeviceCallback = null
    ) {}

    public function getDeviceNames(): array
    {
        return call_user_func($this->deviceNamesCallback);
    }

    public function getDeviceConfig(string $deviceName): ?array
    {
        return call_user_func($this->deviceConfigCallback, $deviceName);
    }

    public function getDefaultDevice(): string
    {
        return $this->defaultDevice;
    }

    public function hasDevice(string $deviceName): bool
    {
        if ($this->hasDeviceCallback) {
            return call_user_func($this->hasDeviceCallback, $deviceName);
        }

        // Fallback: check if device is in available names
        return in_array($deviceName, $this->getDeviceNames(), true);
    }

    public function getGlobalConfig(): array
    {
        return [
            'format' => 'json',
            'logging' => [
                'enabled' => true,
                'channel' => 'stack',
            ],
        ];
    }

    /**
     * Create provider from Eloquent model query
     *
     * Example:
     * ```php
     * $provider = CallbackDeviceProvider::fromEloquent(
     *     Terminal::where('status', 'active'),
     *     nameColumn: 'slug',
     *     configMap: [
     *         'ip' => 'ip_address',
     *         'username' => 'username',
     *         // ...
     *     ]
     * );
     * ```
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string $nameColumn Column to use as device name
     * @param array $configMap Mapping of config keys to model attributes
     * @param string $defaultDevice Default device name
     * @return self
     */
    public static function fromEloquent(
        $query,
        string $nameColumn = 'name',
        array $configMap = [],
        string $defaultDevice = 'primary'
    ): self {
        // Cache results to avoid multiple queries
        $terminals = null;

        $loadTerminals = function () use (&$terminals, $query) {
            if ($terminals === null) {
                $terminals = $query->get();
            }
            return $terminals;
        };

        return new self(
            deviceNamesCallback: function () use ($loadTerminals, $nameColumn) {
                return $loadTerminals()->pluck($nameColumn)->toArray();
            },
            deviceConfigCallback: function (string $deviceName) use ($loadTerminals, $nameColumn, $configMap) {
                $terminal = $loadTerminals()->firstWhere($nameColumn, $deviceName);

                if (!$terminal) {
                    return null;
                }

                // If no config map provided, use default attributes
                if (empty($configMap)) {
                    return [
                        'ip' => $terminal->ip ?? $terminal->ip_address ?? null,
                        'port' => $terminal->port ?? 80,
                        'username' => $terminal->username ?? 'admin',
                        'password' => $terminal->password ?? null,
                        'protocol' => $terminal->protocol ?? 'http',
                        'timeout' => $terminal->timeout ?? 30,
                        'verify_ssl' => $terminal->verify_ssl ?? false,
                    ];
                }

                // Map attributes according to config map
                $config = [];
                foreach ($configMap as $configKey => $attribute) {
                    $config[$configKey] = $terminal->{$attribute} ?? null;
                }

                return $config;
            },
            defaultDevice: $defaultDevice,
            hasDeviceCallback: function (string $deviceName) use ($loadTerminals, $nameColumn) {
                return $loadTerminals()->contains($nameColumn, $deviceName);
            }
        );
    }

    /**
     * Create provider from array of devices
     *
     * Example:
     * ```php
     * $provider = CallbackDeviceProvider::fromArray([
     *     'entrance' => ['ip' => '192.168.1.101', 'username' => 'admin', ...],
     *     'exit' => ['ip' => '192.168.1.102', 'username' => 'admin', ...],
     * ]);
     * ```
     *
     * @param array $devices Array of device configurations
     * @param string $defaultDevice Default device name
     * @return self
     */
    public static function fromArray(array $devices, string $defaultDevice = 'primary'): self
    {
        return new self(
            deviceNamesCallback: fn() => array_keys($devices),
            deviceConfigCallback: fn($name) => $devices[$name] ?? null,
            defaultDevice: $defaultDevice,
            hasDeviceCallback: fn($name) => isset($devices[$name])
        );
    }

    /**
     * Create provider from cache
     *
     * Example:
     * ```php
     * $provider = CallbackDeviceProvider::fromCache(
     *     cacheKey: 'hikvision_terminals',
     *     ttl: 3600
     * );
     * ```
     *
     * @param string $cacheKey Cache key for device list
     * @param int $ttl Cache TTL in seconds
     * @return self
     */
    public static function fromCache(string $cacheKey = 'hikvision_devices', int $ttl = 3600): self
    {
        return new self(
            deviceNamesCallback: function () use ($cacheKey, $ttl) {
                return cache()->remember(
                    "{$cacheKey}_names",
                    $ttl,
                    fn() => \DB::table('terminals')->pluck('name')->toArray()
                );
            },
            deviceConfigCallback: function (string $deviceName) use ($cacheKey, $ttl) {
                return cache()->remember(
                    "{$cacheKey}_{$deviceName}",
                    $ttl,
                    fn() => \DB::table('terminals')->where('name', $deviceName)->first()
                );
            }
        );
    }
}
