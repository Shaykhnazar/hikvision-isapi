<?php

declare(strict_types=1);

namespace Shaykhnazar\HikvisionIsapi\Services;

use Shaykhnazar\HikvisionIsapi\Client\HikvisionClient;
use Shaykhnazar\HikvisionIsapi\Concerns\PagesSearchResults;
use Shaykhnazar\HikvisionIsapi\DTOs\Person;

class PersonService
{
    use PagesSearchResults;

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

        return $response['UserInfoCount']['userNumber'] ?? 0;
    }

    /**
     * One page of the user list.
     *
     * `searchID` identifies a search *session*, and every page of one search must
     * carry the same value — so pass `$searchId` when paginating. Omitting it
     * generates a fresh one, which is correct only for a single-page query.
     *
     * Prefer {@see all()} for walking the whole list: it holds the session open
     * and advances by what the device actually returned, which this method
     * cannot do because it only sees one page.
     *
     * @return list<Person>
     */
    public function search(int $page = 0, int $maxResults = 30, ?string $searchId = null): array
    {
        return $this->searchAt($page * $maxResults, $maxResults, $searchId ?? self::newSearchId())['items'];
    }

    /**
     * Every person the device holds, one page at a time.
     *
     * This is the primitive anything reconciling against a terminal needs. A
     * caller paging by hand cannot know when to stop — the device says so in
     * `responseStatusStrg`, which a single `search()` call discards — and cannot
     * keep the search session open across calls.
     *
     * @return \Generator<int, Person>
     */
    public function all(int $pageSize = 30): \Generator
    {
        if ($pageSize < 1) {
            throw new \InvalidArgumentException('pageSize must be at least 1');
        }

        yield from $this->walkSearch(
            fn (int $position, string $searchId): array => $this->searchAt($position, $pageSize, $searchId),
        );
    }

    /**
     * @return array{items: list<Person>, more: bool}
     */
    private function searchAt(int $position, int $maxResults, string $searchId): array
    {
        $response = $this->client->post(self::ENDPOINT_SEARCH, [
            'UserInfoSearchCond' => [
                'searchID' => $searchId,
                'searchResultPosition' => $position,
                'maxResults' => $maxResults,
            ],
        ]);

        $result = $response['UserInfoSearch'] ?? [];

        $persons = [];

        foreach (self::normalizeRecords($result['UserInfo'] ?? []) as $personData) {
            $persons[] = Person::fromArray(['UserInfo' => $personData]);
        }

        return ['items' => $persons, 'more' => self::saysMore($result)];
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
                    fn ($no) => ['employeeNo' => $no],
                    $employeeNos
                ),
            ],
        ];

        return $this->client->put(self::ENDPOINT_DELETE, $data);
    }

    /**
     * Upload face image for a person using multipart form-data
     * For Access Control devices that support face recognition
     *
     * @param  string  $employeeNo  Employee number
     * @param  string  $imageData  Binary image data (JPEG)
     * @return array Response from device
     */
    public function uploadFace(string $employeeNo, string $imageData): array
    {
        $multipart = [
            [
                'name' => 'UserInfo',
                'contents' => json_encode([
                    'employeeNo' => $employeeNo,
                ]),
            ],
            [
                'name' => 'FaceImage',
                'contents' => $imageData,
                'filename' => 'face.jpg',
                'headers' => [
                    'Content-Type' => 'image/jpeg',
                ],
            ],
        ];

        return $this->client->putMultipart(self::ENDPOINT_MODIFY, $multipart);
    }
}
