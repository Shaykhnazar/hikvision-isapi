<?php

declare(strict_types=1);

namespace Shaykhnazar\HikvisionIsapi\Services;

use Shaykhnazar\HikvisionIsapi\Client\HikvisionClient;

class DeviceService
{
    private const ENDPOINT_INFO = '/ISAPI/System/deviceInfo';
    private const ENDPOINT_CAPABILITIES = '/ISAPI/AccessControl/capabilities';
    private const ENDPOINT_STATUS = '/ISAPI/System/status';

    public function __construct(
        private readonly HikvisionClient $client
    ) {}

    public function getInfo(): array
    {
        return $this->client->get(self::ENDPOINT_INFO);
    }

    public function getCapabilities(): array
    {
        return $this->client->get(self::ENDPOINT_CAPABILITIES);
    }

    public function getStatus(): array
    {
        return $this->client->get(self::ENDPOINT_STATUS);
    }

    public function isOnline(): bool
    {
        try {
            $this->getInfo();
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }
}
