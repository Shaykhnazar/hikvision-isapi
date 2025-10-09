<?php

declare(strict_types=1);

namespace Shaykhnazar\HikvisionIsapi\Services;

use Shaykhnazar\HikvisionIsapi\Client\HikvisionClient;
use Shaykhnazar\HikvisionIsapi\DTOs\Card;
use Shaykhnazar\HikvisionIsapi\Exceptions\HikvisionException;

class CardService
{
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

    public function search(
        int $page = 0,
        int $maxResults = 30,
        ?string $employeeNo = null,
        ?string $cardNo = null
    ): array {
        $data = [
            'CardInfoSearchCond' => [
                'searchID' => (string) time(),
                'searchResultPosition' => $page * $maxResults,
                'maxResults' => $maxResults,
            ],
        ];

        if ($employeeNo) {
            $data['CardInfoSearchCond']['employeeNo'] = $employeeNo;
        }

        if ($cardNo) {
            $data['CardInfoSearchCond']['cardNo'] = $cardNo;
        }

        $response = $this->client->post(self::ENDPOINT_SEARCH, $data);

        $cards = [];
        if (isset($response['CardInfoSearch']['CardInfo'])) {
            foreach ($response['CardInfoSearch']['CardInfo'] as $cardData) {
                $cards[] = Card::fromArray(['CardInfo' => $cardData]);
            }
        }

        return $cards;
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
                    fn($no) => ['employeeNo' => $no],
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
