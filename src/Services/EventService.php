<?php

declare(strict_types=1);

namespace Shaykhnazar\HikvisionIsapi\Services;

use Shaykhnazar\HikvisionIsapi\Client\HikvisionClient;

class EventService
{
    private const ENDPOINT_SEARCH = '/ISAPI/AccessControl/AcsEvent';
    private const ENDPOINT_COUNT = '/ISAPI/AccessControl/AcsEventTotalNum';
    private const ENDPOINT_SUBSCRIBE = '/ISAPI/Event/notification/subscribeEvent';
    private const ENDPOINT_ALERT_STREAM = '/ISAPI/Event/notification/alertStream';

    public function __construct(
        private readonly HikvisionClient $client
    ) {}

    public function search(array $conditions, int $page = 0, int $maxResults = 30): array
    {
        $data = [
            'AcsEventCond' => array_merge($conditions, [
                'searchID' => (string) time(),
                'searchResultPosition' => $page * $maxResults,
                'maxResults' => $maxResults,
            ]),
        ];

        return $this->client->post(self::ENDPOINT_SEARCH, $data);
    }

    public function count(array $conditions): int
    {
        $data = ['AcsEventTotalNumCond' => $conditions];

        $response = $this->client->post(self::ENDPOINT_COUNT, $data);
        return $response['totalNum'] ?? 0;
    }

    public function subscribe(array $eventTypes = [], int $heartbeat = 60): array
    {
        $data = [
            'SubscribeEvent' => [
                'eventMode' => 'list',
                'eventList' => $eventTypes,
                'heartbeat' => $heartbeat,
            ],
        ];

        return $this->client->post(self::ENDPOINT_SUBSCRIBE, $data);
    }
}
