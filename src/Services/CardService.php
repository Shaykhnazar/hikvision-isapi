<?php

declare(strict_types=1);

namespace Shaykhnazar\HikvisionIsapi\Services;

use Shaykhnazar\HikvisionIsapi\Client\HikvisionClient;
use Shaykhnazar\HikvisionIsapi\Concerns\PagesSearchResults;
use Shaykhnazar\HikvisionIsapi\DTOs\Card;
use Shaykhnazar\HikvisionIsapi\Exceptions\HikvisionException;

class CardService
{
    use PagesSearchResults;

    private const ENDPOINT_CAPABILITIES = '/ISAPI/AccessControl/CardInfo/capabilities';

    private const ENDPOINT_COUNT = '/ISAPI/AccessControl/CardInfo/Count';

    private const ENDPOINT_SEARCH = '/ISAPI/AccessControl/CardInfo/Search';

    private const ENDPOINT_RECORD = '/ISAPI/AccessControl/CardInfo/Record';

    private const ENDPOINT_MODIFY = '/ISAPI/AccessControl/CardInfo/Modify';

    private const ENDPOINT_DELETE = '/ISAPI/AccessControl/CardInfo/Delete';

    public function __construct(
        private readonly HikvisionClient $client
    ) {}

    public function getCapabilities(): array
    {
        return $this->client->get(self::ENDPOINT_CAPABILITIES);
    }

    public function count(?string $employeeNo = null): int
    {
        $params = $employeeNo ? ['employeeNo' => $employeeNo] : [];
        $response = $this->client->get(self::ENDPOINT_COUNT, $params);

        return $response['CardInfo']['cardNumber'] ?? 0;
    }

    /**
     * One page of the card list.
     *
     * `searchID` identifies a search *session*: every page of one search must
     * carry the same value, so pass `$searchId` when paginating. Omitting it
     * generates a fresh one, which is correct only for a single-page query.
     *
     * Prefer {@see all()} for walking the whole list.
     *
     * @return list<Card>
     */
    public function search(
        int $page = 0,
        int $maxResults = 30,
        ?string $employeeNo = null,
        ?string $cardNo = null,
        ?string $searchId = null,
    ): array {
        return $this->searchAt(
            $page * $maxResults,
            $maxResults,
            $searchId ?? self::newSearchId(),
            $employeeNo,
            $cardNo,
        )['items'];
    }

    /**
     * Every card the device holds, one page at a time.
     *
     * @return \Generator<int, Card>
     */
    public function all(int $pageSize = 30, ?string $employeeNo = null, ?string $cardNo = null): \Generator
    {
        if ($pageSize < 1) {
            throw new \InvalidArgumentException('pageSize must be at least 1');
        }

        yield from $this->walkSearch(
            fn (int $position, string $searchId): array => $this->searchAt(
                $position,
                $pageSize,
                $searchId,
                $employeeNo,
                $cardNo,
            ),
        );
    }

    /**
     * @return array{items: list<Card>, more: bool}
     */
    private function searchAt(
        int $position,
        int $maxResults,
        string $searchId,
        ?string $employeeNo,
        ?string $cardNo,
    ): array {
        $condition = [
            'searchID' => $searchId,
            'searchResultPosition' => $position,
            'maxResults' => $maxResults,
        ];

        if ($employeeNo !== null && $employeeNo !== '') {
            $condition['employeeNo'] = $employeeNo;
        }

        if ($cardNo !== null && $cardNo !== '') {
            $condition['cardNo'] = $cardNo;
        }

        $response = $this->client->post(self::ENDPOINT_SEARCH, ['CardInfoSearchCond' => $condition]);

        $result = $response['CardInfoSearch'] ?? [];

        $cards = [];

        foreach (self::normalizeRecords($result['CardInfo'] ?? []) as $cardData) {
            $cards[] = Card::fromArray(['CardInfo' => $cardData]);
        }

        return ['items' => $cards, 'more' => self::saysMore($result)];
    }

    /**
     * A device returns a bare record when a page holds exactly one, and a list
     * otherwise. Normalise both into a list.
     *
     * @param  array<mixed>  $records
     * @return list<array<string, mixed>>
     */
    private static function normalizeRecords(array $records): array
    {
        if ($records === []) {
            return [];
        }

        return array_is_list($records) ? $records : [$records];
    }

    public function add(Card $card): array
    {
        return $this->client->post(self::ENDPOINT_RECORD, $card->toArray());
    }

    public function update(Card $card): array
    {
        return $this->client->put(self::ENDPOINT_MODIFY, $card->toArray());
    }

    public function delete(array $employeeNos): array
    {
        $data = [
            'CardInfoDelCond' => [
                'EmployeeNoList' => array_map(
                    fn ($no) => ['employeeNo' => $no],
                    $employeeNos
                ),
            ],
        ];

        return $this->client->put(self::ENDPOINT_DELETE, $data);
    }

    public function deleteAll(): array
    {
        $data = [
            'CardInfoDelCond' => [
                'mode' => 'all',
            ],
        ];

        return $this->client->put(self::ENDPOINT_DELETE, $data);
    }

    public function batchAdd(array $cards): array
    {
        $results = [
            'total' => count($cards),
            'success' => 0,
            'failed' => 0,
            'errors' => [],
        ];

        foreach ($cards as $card) {
            try {
                $this->add($card);
                $results['success']++;
            } catch (HikvisionException $e) {
                $results['failed']++;
                $results['errors'][] = [
                    'card' => $card->cardNo,
                    'error' => $e->getMessage(),
                ];
            }
        }

        return $results;
    }
}
