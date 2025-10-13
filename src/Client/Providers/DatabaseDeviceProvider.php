<?php

declare(strict_types=1);

namespace Shaykhnazar\HikvisionIsapi\Client\Providers;

use Illuminate\Support\Facades\DB;
use Shaykhnazar\HikvisionIsapi\Client\Contracts\DeviceProviderInterface;

/**
 * Database-based device provider
 *
 * Loads device configurations from database.
 * Useful for multi-tenant applications where terminals are stored in DB.
 *
 * Example usage:
 * ```php
 * $provider = new DatabaseDeviceProvider(
 *     table: 'terminals',
 *     nameColumn: 'name',
 *     configColumns: [
 *         'ip' => 'ip_address',
 *         'port' => 'port',
 *         'username' => 'username',
 *         'password' => 'password',
 *         'protocol' => 'protocol',
 *         'timeout' => 'timeout',
 *         'verify_ssl' => 'verify_ssl',
 *     ],
 *     defaultDevice: 'primary',
 *     cache: true, // Enable caching
 *     cacheTtl: 3600 // Cache for 1 hour
 * );
 * ```
 */
class DatabaseDeviceProvider implements DeviceProviderInterface
{
    private array $cachedDevices = [];
    private ?int $cacheTime = null;

    /**
     * @param string $table Database table name
     * @param string $nameColumn Column name for device name/identifier
     * @param array $configColumns Mapping of config keys to database columns
     * @param string $defaultDevice Default device name
     * @param array|null $whereConditions Additional WHERE conditions (e.g., ['status' => 'active'])
     * @param bool $cache Enable in-memory caching
     * @param int $cacheTtl Cache TTL in seconds (for future Redis/cache driver support)
     */
    public function __construct(
        private readonly string $table = 'terminals',
        private readonly string $nameColumn = 'name',
        private readonly array $configColumns = [
            'ip' => 'ip',
            'port' => 'port',
            'username' => 'username',
            'password' => 'password',
            'protocol' => 'protocol',
            'timeout' => 'timeout',
            'verify_ssl' => 'verify_ssl',
        ],
        private readonly string $defaultDevice = 'primary',
        private readonly ?array $whereConditions = null,
        private readonly bool $cache = true,
        private readonly int $cacheTtl = 3600
    ) {}

    public function getDeviceNames(): array
    {
        $this->loadDevices();

        return array_keys($this->cachedDevices);
    }

    public function getDeviceConfig(string $deviceName): ?array
    {
        $this->loadDevices();

        return $this->cachedDevices[$deviceName] ?? null;
    }

    public function getDefaultDevice(): string
    {
        return $this->defaultDevice;
    }

    public function hasDevice(string $deviceName): bool
    {
        $this->loadDevices();

        return isset($this->cachedDevices[$deviceName]);
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
     * Clear cached devices (useful when terminals are updated in DB)
     */
    public function clearCache(): void
    {
        $this->cachedDevices = [];
        $this->cacheTime = null;
    }

    /**
     * Load devices from database
     */
    private function loadDevices(): void
    {
        // Check if cache is still valid
        if ($this->cache && !empty($this->cachedDevices)) {
            if ($this->cacheTime && (time() - $this->cacheTime) < $this->cacheTtl) {
                return;
            }
        }

        $query = DB::table($this->table);

        // Apply WHERE conditions if provided
        if ($this->whereConditions) {
            $query->where($this->whereConditions);
        }

        $terminals = $query->get();

        $this->cachedDevices = [];

        foreach ($terminals as $terminal) {
            $deviceName = $terminal->{$this->nameColumn};

            // Map database columns to device config
            $config = [];
            foreach ($this->configColumns as $configKey => $dbColumn) {
                $value = $terminal->{$dbColumn} ?? null;

                // Cast boolean values
                if (in_array($configKey, ['verify_ssl'])) {
                    $value = (bool) $value;
                }

                // Cast integer values
                if (in_array($configKey, ['port', 'timeout'])) {
                    $value = (int) $value;
                }

                $config[$configKey] = $value;
            }

            $this->cachedDevices[$deviceName] = $config;
        }

        $this->cacheTime = time();
    }

    /**
     * Create provider from model
     *
     * Example:
     * ```php
     * $provider = DatabaseDeviceProvider::fromModel(
     *     model: Terminal::class,
     *     nameColumn: 'slug',
     *     configColumns: ['ip' => 'ip_address', ...],
     *     query: fn($query) => $query->where('status', 'active')
     * );
     * ```
     *
     * @param string $model Eloquent model class
     * @param string $nameColumn Column for device name
     * @param array $configColumns Config mapping
     * @param callable|null $query Additional query builder callback
     * @param string $defaultDevice Default device name
     * @return self
     */
    public static function fromModel(
        string $model,
        string $nameColumn = 'name',
        array $configColumns = [],
        ?callable $query = null,
        string $defaultDevice = 'primary'
    ): self {
        // Get table name from model
        $instance = new $model;
        $table = $instance->getTable();

        // If query callback provided, we'll need to handle it differently
        // For now, we'll use a simplified approach with WHERE conditions
        // In the future, this could be extended to support complex queries

        return new self(
            table: $table,
            nameColumn: $nameColumn,
            configColumns: $configColumns,
            defaultDevice: $defaultDevice
        );
    }
}
