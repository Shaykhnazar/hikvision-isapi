<?php

declare(strict_types=1);

namespace Shaykhnazar\HikvisionIsapi\Services;

use Shaykhnazar\HikvisionIsapi\Client\HikvisionClient;
use Shaykhnazar\HikvisionIsapi\DTOs\Person;

class PersonService
{
    private const ENDPOINT_CAPABILITIES = '/ISAPI/AccessControl/UserInfo/Capabilities';
    private const ENDPOINT_COUNT = '/ISAPI/AccessControl/UserInfo/Count';
    private const ENDPOINT_SEARCH = '/ISAPI/AccessControl/UserInfo/Search';
    private const ENDPOINT_RECORD = '/ISAPI/AccessControl/UserInfo/Record';
    private const ENDPOINT_MODIFY = '/ISAPI/AccessControl/UserInfo/Modify';
    private const ENDPOINT_SETUP = '/ISAPI/AccessControl/UserInfo/SetUp';
    private const ENDPOINT_DELETE = '/ISAPI/AccessControl/UserInfo/Delete';

    public function __construct(
        private readonly HikvisionClient $client
    ) {}

    public function getCapabilities(): array
    {
        return $this->client->get(self::ENDPOINT_CAPABILITIES);
    }

    public function count(): int
    {
        $response = $this->client->get(self::ENDPOINT_COUNT);
        return $response['UserInfo']['userNumber'] ?? 0;
    }

    public function search(int $page = 0, int $maxResults = 30): array
    {
        $data = [
            'UserInfoSearchCond' => [
                'searchID' => (string) time(),
                'searchResultPosition' => $page * $maxResults,
                'maxResults' => $maxResults,
            ],
        ];

        $response = $this->client->post(self::ENDPOINT_SEARCH, $data);

        $persons = [];
        if (isset($response['UserInfoSearch']['UserInfo'])) {
            foreach ($response['UserInfoSearch']['UserInfo'] as $personData) {
                $persons[] = Person::fromArray(['UserInfo' => $personData]);
            }
        }

        return $persons;
    }

    public function add(Person $person): array
    {
        return $this->client->post(self::ENDPOINT_RECORD, $person->toArray());
    }

    public function update(Person $person): array
    {
        return $this->client->put(self::ENDPOINT_MODIFY, $person->toArray());
    }

    public function apply(Person $person): array
    {
        return $this->client->put(self::ENDPOINT_SETUP, $person->toArray());
    }

    public function delete(array $employeeNos): array
    {
        $data = [
            'UserInfoDelCond' => [
                'EmployeeNoList' => array_map(
                    fn($no) => ['employeeNo' => $no],
                    $employeeNos
                ),
            ],
        ];

        return $this->client->put(self::ENDPOINT_DELETE, $data);
    }
}
