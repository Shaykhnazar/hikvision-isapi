<?php

declare(strict_types=1);

namespace Shaykhnazar\HikvisionIsapi\Services;

use Shaykhnazar\HikvisionIsapi\Client\HikvisionClient;
use Shaykhnazar\HikvisionIsapi\Concerns\PagesSearchResults;

class EventService
{
    use PagesSearchResults;

    private const ENDPOINT_SEARCH = '/ISAPI/AccessControl/AcsEvent';

    private const ENDPOINT_COUNT = '/ISAPI/AccessControl/AcsEventTotalNum';

    private const ENDPOINT_SUBSCRIBE = '/ISAPI/Event/notification/subscribeEvent';

    /**
     * Response status a device returns while more pages of the same search remain.
     */
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

        $conditions = array_merge($extraConditions, [
            'startTime' => $from->format(\DateTimeInterface::ATOM),
            'endTime' => $to->format(\DateTimeInterface::ATOM),
        ]);

        // The session, the advance-by-returned and the stop condition all live in
        // PagesSearchResults now, so events, people and cards cannot drift into
        // three subtly different ideas of how ISAPI paging works.
        yield from $this->walkSearch(
            function (int $position, string $searchId) use ($conditions, $pageSize): array {
                $response = $this->searchAt($conditions, $position, $pageSize, $searchId);
                $result = $response['AcsEvent'] ?? [];

                return [
                    'items' => self::normalizeInfoList($result['InfoList'] ?? []),
                    'more' => self::saysMore($result),
                ];
            },
        );
    }

    /**
     * @param  array<string, mixed>  $conditions
     */
    /**
     * How many events match, according to the device.
     *
     * The count is read from either the nested `AcsEventTotalNum` block or the
     * top level, because the endpoint is documented to answer with the former
     * and this client previously only looked at the latter — which returns zero
     * for every device that follows the documentation, silently and with nothing
     * to distinguish it from "no events".
     *
     * Not verified against hardware: which shape a real terminal sends is one of
     * the things the first installation has to answer. Reading both is what
     * makes the answer not matter.
     *
     * @param  array<string, mixed>  $conditions
     */
    public function count(array $conditions): int
    {
        $response = $this->client->post(self::ENDPOINT_COUNT, ['AcsEventTotalNumCond' => $conditions]);

        $nested = $response['AcsEventTotalNum'] ?? [];

        $total = (is_array($nested) ? ($nested['totalNum'] ?? null) : null)
            ?? $response['totalNum']
            ?? 0;

        return is_numeric($total) ? (int) $total : 0;
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
