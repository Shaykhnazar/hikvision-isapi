<?php

declare(strict_types=1);

namespace Shaykhnazar\HikvisionIsapi\Tests\Unit\Client;

use PHPUnit\Framework\TestCase;
use Shaykhnazar\HikvisionIsapi\Authentication\DigestAuthenticator;
use Shaykhnazar\HikvisionIsapi\Client\DeviceManager;
use Shaykhnazar\HikvisionIsapi\Client\HikvisionClient;
use Shaykhnazar\HikvisionIsapi\Client\HttpClient;
use Shaykhnazar\HikvisionIsapi\Client\Providers\CallbackDeviceProvider;
use Shaykhnazar\HikvisionIsapi\Client\Providers\ConfigDeviceProvider;
use Shaykhnazar\HikvisionIsapi\Exceptions\HikvisionException;

class DeviceManagerTest extends TestCase
{
    /**
     * @return array<string, mixed>
     */
    private function deviceConfig(string $ip): array
    {
        return [
            'ip' => $ip,
            'port' => 80,
            'username' => 'admin',
            'password' => 'secret',
            'protocol' => 'http',
            'timeout' => 30,
            'verify_ssl' => false,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function config(): array
    {
        return [
            'default' => 'entrance',
            'format' => 'json',
            'devices' => [
                'entrance' => $this->deviceConfig('192.168.1.10'),
                'exit' => $this->deviceConfig('192.168.1.11'),
            ],
        ];
    }

    private function manager(mixed $provider = null): DeviceManager
    {
        return new DeviceManager(
            new HttpClient,
            new DigestAuthenticator,
            $provider ?? $this->config()
        );
    }

    public function test_legacy_array_config_is_wrapped_in_config_provider(): void
    {
        $manager = $this->manager();

        $this->assertInstanceOf(ConfigDeviceProvider::class, $manager->getProvider());
    }

    public function test_available_devices_are_listed(): void
    {
        $this->assertSame(['entrance', 'exit'], $this->manager()->availableDevices());
    }

    public function test_default_device_is_used_when_no_name_given(): void
    {
        $manager = $this->manager();

        $this->assertSame($manager->device('entrance'), $manager->default());
    }

    public function test_clients_are_cached_per_device(): void
    {
        $manager = $this->manager();

        $this->assertSame($manager->device('exit'), $manager->device('exit'));
        $this->assertNotSame($manager->device('entrance'), $manager->device('exit'));
    }

    public function test_clear_clients_discards_cached_instances(): void
    {
        $manager = $this->manager();
        $first = $manager->device('entrance');

        $manager->clearClients();

        $this->assertNotSame($first, $manager->device('entrance'));
    }

    public function test_unknown_device_throws(): void
    {
        $this->expectException(HikvisionException::class);
        $this->expectExceptionMessage("Device 'canteen' not found in configuration");

        $this->manager()->device('canteen');
    }

    public function test_switch_device_reports_the_device_it_could_not_switch_to(): void
    {
        $this->expectException(HikvisionException::class);
        $this->expectExceptionMessage("Cannot switch to device 'canteen'");

        $this->manager()->switchDevice('canteen');
    }

    public function test_missing_default_device_throws_runtime_exception(): void
    {
        $provider = new CallbackDeviceProvider(
            deviceNamesCallback: fn () => [],
            deviceConfigCallback: fn (string $name) => null,
            defaultDevice: null,
        );

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('No device name provided');

        $this->manager($provider)->device();
    }

    public function test_has_device_delegates_to_provider(): void
    {
        $manager = $this->manager();

        $this->assertTrue($manager->hasDevice('entrance'));
        $this->assertFalse($manager->hasDevice('canteen'));
    }

    public function test_set_provider_replaces_devices_and_clears_cache(): void
    {
        $manager = $this->manager();
        $before = $manager->device('entrance');

        $manager->setProvider(new CallbackDeviceProvider(
            deviceNamesCallback: fn () => ['gate'],
            deviceConfigCallback: fn (string $name) => $this->deviceConfig('10.0.0.5'),
            defaultDevice: 'gate',
        ));

        $this->assertSame(['gate'], $manager->availableDevices());
        $this->assertNotSame($before, $manager->device('gate'));
    }

    public function test_register_device_adds_a_runtime_client(): void
    {
        $manager = $this->manager();

        $manager->registerDevice('temporary', $this->deviceConfig('10.0.0.9'));

        $this->assertInstanceOf(HikvisionClient::class, $manager->device('temporary'));
    }

    public function test_callback_provider_resolves_default_device_lazily(): void
    {
        $current = 'entrance';

        $provider = new CallbackDeviceProvider(
            deviceNamesCallback: fn () => ['entrance', 'exit'],
            deviceConfigCallback: fn (string $name) => $this->deviceConfig('192.168.1.10'),
            defaultDevice: function () use (&$current) {
                return $current;
            },
        );

        $this->assertSame('entrance', $provider->getDefaultDevice());

        $current = 'exit';

        $this->assertSame('exit', $provider->getDefaultDevice());
    }
}
