<?php

declare(strict_types=1);

namespace Shaykhnazar\HikvisionIsapi\Services;

use Shaykhnazar\HikvisionIsapi\Client\HikvisionClient;

class EventService
{
    private const ENDPOINT_SEARCH = '/ISAPI/AccessControl/AcsEvent';

    private const ENDPOINT_COUNT = '/ISAPI/AccessControl/AcsEventTotalNum';

    private const ENDPOINT_SUBSCRIBE = '/ISAPI/Event/notification/subscribeEvent';

    /**
     * Response status a device returns while more pages of the same search remain.
     */
    private const STATUS_MORE = 'MORE';

    public function __construct(
        private readonly HikvisionClient $client
    ) {}

    /**
     * Search access-control events.
     *
     * ISAPI treats `searchID` as the identity of a search session, and every page
     * of one search must reuse the same value. Pass `$searchId` explicitly when
     * paginating; when it is omitted a fresh one is generated per call, which is
     * only correct for a single-page query.
     *
     * @param  array<string, mixed>  $conditions
     * @return array<string, mixed>
     */
    public function search(array $conditions, int $page = 0, int $maxResults = 30, ?string $searchId = null): array
    {
        return $this->searchAt(
            $conditions,
            $page * $maxResults,
            $maxResults,
            $searchId ?? self::newSearchId()
        );
    }

    /**
     * Iterate every event in a time window, one page at a time.
     *
     * This is the backfill primitive: webhook delivery from a terminal is
     * best-effort, so anything that needs a complete event stream has to re-read
     * the window from the device and reconcile. The whole search reuses a single
     * `searchID`, advances by the number of records the device actually returned,
     * and stops as soon as the device reports anything other than "MORE".
     *
     * Events are yielded as they arrive, so a wide window does not have to be
     * held in memory.
     *
     * @param  array<string, mixed>  $extraConditions  Additional AcsEventCond fields, e.g. major/minor filters.
     * @return \Generator<int, array<string, mixed>>
     */
    public function between(
        \DateTimeInterface $from,
        \DateTimeInterface $to,
        array $extraConditions = [],
        int $pageSize = 30,
    ): \Generator {
        if ($pageSize < 1) {
            throw new \InvalidArgumentException('pageSize must be at least 1');
        }

        if ($from > $to) {
            throw new \InvalidArgumentException('from must not be later than to');
        }

        $searchId = self::newSearchId();
        $conditions = array_merge($extraConditions, [
            'startTime' => $from->format(\DateTimeInterface::ATOM),
            'endTime' => $to->format(\DateTimeInterface::ATOM),
        ]);

        $position = 0;

        do {
            $response = $this->searchAt($conditions, $position, $pageSize, $searchId);

            $result = $response['AcsEvent'] ?? [];
            $events = self::normalizeInfoList($result['InfoList'] ?? []);

            foreach ($events as $event) {
                yield $event;
            }

            $returned = count($events);
            $position += $returned;

            // The empty-page guard matters as much as the status check: a device
            // that keeps reporting MORE while returning nothing would otherwise
            // spin forever.
            $hasMore = ($result['responseStatusStrg'] ?? '') === self::STATUS_MORE && $returned > 0;
        } while ($hasMore);
    }

    /**
     * @param  array<string, mixed>  $conditions
     */
    public function count(array $conditions): int
    {
        $data = ['AcsEventTotalNumCond' => $conditions];

        $response = $this->client->post(self::ENDPOINT_COUNT, $data);

        return $response['totalNum'] ?? 0;
    }

    /**
     * @param  array<int, string>  $eventTypes
     * @return array<string, mixed>
     */
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

    /**
     * @param  array<string, mixed>  $conditions
     * @return array<string, mixed>
     */
    private function searchAt(array $conditions, int $position, int $maxResults, string $searchId): array
    {
        $data = [
            'AcsEventCond' => array_merge($conditions, [
                'searchID' => $searchId,
                'searchResultPosition' => $position,
                'maxResults' => $maxResults,
            ]),
        ];

        return $this->client->post(self::ENDPOINT_SEARCH, $data);
    }

    private static function newSearchId(): string
    {
        return bin2hex(random_bytes(8));
    }

    /**
     * A device returns a bare record when a page holds exactly one event, and a
     * list otherwise. Normalise both into a list.
     *
     * @return list<array<string, mixed>>
     */
    private static function normalizeInfoList(mixed $infoList): array
    {
        if (!is_array($infoList) || $infoList === []) {
            return [];
        }

        return array_is_list($infoList) ? $infoList : [$infoList];
    }
}
