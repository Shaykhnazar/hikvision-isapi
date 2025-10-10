<?php

declare(strict_types=1);

namespace Shaykhnazar\HikvisionIsapi\Tests\Unit\Services;

use Mockery;
use PHPUnit\Framework\TestCase;
use Shaykhnazar\HikvisionIsapi\Client\HikvisionClient;
use Shaykhnazar\HikvisionIsapi\Services\DeviceService;

class DeviceServiceTest extends TestCase
{
    private HikvisionClient $mockClient;
    private DeviceService $deviceService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mockClient = Mockery::mock(HikvisionClient::class);
        $this->deviceService = new DeviceService($this->mockClient);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_can_get_device_info(): void
    {
        $mockInfo = [
            'DeviceInfo' => [
                'deviceName' => 'Test Device',
                'model' => 'DS-K1T671M',
                'serialNumber' => '123456789',
            ],
        ];

        $this->mockClient
            ->shouldReceive('get')
            ->with('/ISAPI/System/deviceInfo')
            ->once()
            ->andReturn($mockInfo);

        $info = $this->deviceService->getInfo();

        $this->assertArrayHasKey('DeviceInfo', $info);
        $this->assertSame('Test Device', $info['DeviceInfo']['deviceName']);
    }

    public function test_can_get_device_capabilities(): void
    {
        $mockCapabilities = [
            'AccessControlCap' => [
                'supportUserManagement' => true,
                'supportCardManagement' => true,
            ],
        ];

        $this->mockClient
            ->shouldReceive('get')
            ->with('/ISAPI/AccessControl/capabilities')
            ->once()
            ->andReturn($mockCapabilities);

        $capabilities = $this->deviceService->getCapabilities();

        $this->assertArrayHasKey('AccessControlCap', $capabilities);
    }

    public function test_can_check_device_is_online(): void
    {
        $this->mockClient
            ->shouldReceive('get')
            ->with('/ISAPI/System/deviceInfo')
            ->once()
            ->andReturn(['DeviceInfo' => []]);

        $isOnline = $this->deviceService->isOnline();

        $this->assertTrue($isOnline);
    }

    public function test_returns_false_when_device_is_offline(): void
    {
        $this->mockClient
            ->shouldReceive('get')
            ->with('/ISAPI/System/deviceInfo')
            ->once()
            ->andThrow(new \Exception('Connection failed'));

        $isOnline = $this->deviceService->isOnline();

        $this->assertFalse($isOnline);
    }
}
