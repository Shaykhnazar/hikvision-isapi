<?php

declare(strict_types=1);

namespace Shaykhnazar\HikvisionIsapi\Tests\Unit\Services;

use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\TestCase;
use Shaykhnazar\HikvisionIsapi\Client\HikvisionClient;
use Shaykhnazar\HikvisionIsapi\DTOs\Person;
use Shaykhnazar\HikvisionIsapi\Services\PersonService;

/**
 * Walking a terminal's user list across pages.
 *
 * The reason this matters beyond tidiness: anything reconciling against a device
 * — "who does this terminal actually hold?" — is built on this walk. If it
 * silently stops early or repeats a page, the caller does not get an error. It
 * gets a shorter list, believes it, and concludes that everybody missing from it
 * has been deleted from the device.
 */
class PersonSearchPagingTest extends TestCase
{
    /** @var HikvisionClient&MockInterface */
    private HikvisionClient $mockClient;

    private PersonService $persons;

    /** @var list<array<string, mixed>> */
    private array $sentConditions = [];

    protected function setUp(): void
    {
        parent::setUp();

        /** @var HikvisionClient&MockInterface $client */
        $client = Mockery::mock(HikvisionClient::class);

        $this->mockClient = $client;
        $this->persons = new PersonService($this->mockClient);
        $this->sentConditions = [];
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /**
     * @param  list<array<string, mixed>>  $pages
     */
    private function queuePages(array $pages): void
    {
        $this->mockClient
            ->shouldReceive('post')
            ->with('/ISAPI/AccessControl/UserInfo/Search', Mockery::type('array'))
            ->times(count($pages))
            ->andReturnUsing(function (string $endpoint, array $data) use (&$pages) {
                $this->sentConditions[] = $data['UserInfoSearchCond'];

                return array_shift($pages);
            });
    }

    /**
     * @param  list<string>  $employeeNos
     * @return array<string, mixed>
     */
    private function page(array $employeeNos, string $status): array
    {
        return [
            'UserInfoSearch' => [
                'responseStatusStrg' => $status,
                'UserInfo' => array_map(static fn (string $no): array => [
                    'employeeNo' => $no,
                    'name' => 'Person '.$no,
                    'userType' => 'normal',
                    'Valid' => ['enable' => true],
                ], $employeeNos),
            ],
        ];
    }

    /** @return list<string> */
    private function walk(int $pageSize = 2): array
    {
        return array_map(
            static fn (Person $person): string => $person->employeeNo,
            iterator_to_array($this->persons->all($pageSize), false),
        );
    }

    /**
     * The bug this whole change exists for.
     *
     * `searchID` identifies a search *session*, not a request. The previous
     * implementation sent `(string) time()`, so two pages requested either side
     * of a second boundary carried different ids — and the device, seeing a new
     * id, starts a new search and serves page one again.
     */
    public function test_every_page_of_one_walk_carries_the_same_search_id(): void
    {
        $this->queuePages([
            $this->page(['E-1', 'E-2'], 'MORE'),
            $this->page(['E-3', 'E-4'], 'MORE'),
            $this->page(['E-5'], 'OK'),
        ]);

        $this->walk();

        $ids = array_column($this->sentConditions, 'searchID');

        $this->assertCount(3, $ids);
        $this->assertCount(1, array_unique($ids), 'one search session, not three');
        $this->assertNotSame('', $ids[0]);

        // Equality alone does not prove the fix. The old implementation sent
        // `(string) time()`, and three pages fetched inside one second are
        // equal too — the bug only appears when a walk straddles a second
        // boundary, which no test can force without a clock seam.
        //
        // What can be asserted deterministically is that the id is not taken
        // from the wall clock at all, which is the property that makes it stable
        // however long the walk takes.
        $this->assertFalse(
            is_numeric($ids[0]),
            'a clock-derived id changes mid-walk; the session id must not be one',
        );
    }

    /**
     * Two walks are two searches and must not share an id, or a device could
     * treat the second as a continuation of the first.
     */
    public function test_separate_walks_get_separate_search_ids(): void
    {
        $this->queuePages([
            $this->page(['E-1'], 'OK'),
            $this->page(['E-1'], 'OK'),
        ]);

        $this->walk();
        $this->walk();

        $ids = array_column($this->sentConditions, 'searchID');

        $this->assertNotSame($ids[0], $ids[1]);
    }

    public function test_it_follows_pages_until_the_device_stops_saying_more(): void
    {
        $this->queuePages([
            $this->page(['E-1', 'E-2'], 'MORE'),
            $this->page(['E-3', 'E-4'], 'MORE'),
            $this->page(['E-5'], 'OK'),
        ]);

        $this->assertSame(['E-1', 'E-2', 'E-3', 'E-4', 'E-5'], $this->walk());
    }

    /**
     * The device decides how many records a page holds; it may return fewer than
     * asked for and still have more. Advancing by page-number times page-size
     * would skip whatever it held back.
     */
    public function test_position_advances_by_records_returned_not_by_page_size(): void
    {
        $this->queuePages([
            // Asked for 3, gave 2, still has more.
            $this->page(['E-1', 'E-2'], 'MORE'),
            $this->page(['E-3', 'E-4', 'E-5'], 'OK'),
        ]);

        $this->walk(pageSize: 3);

        $this->assertSame([0, 2], array_column($this->sentConditions, 'searchResultPosition'));
    }

    /**
     * A device that keeps saying MORE while returning nothing would otherwise
     * spin forever — and this walk holds the tick of an agent that also has to
     * answer terminal callbacks.
     */
    public function test_an_empty_page_ends_the_walk_even_when_the_device_says_more(): void
    {
        $this->queuePages([
            $this->page(['E-1'], 'MORE'),
            $this->page([], 'MORE'),
        ]);

        $this->assertSame(['E-1'], $this->walk());
    }

    public function test_a_single_short_page_is_the_whole_list(): void
    {
        $this->queuePages([$this->page(['E-1'], 'OK')]);

        $this->assertSame(['E-1'], $this->walk());
    }

    public function test_an_empty_terminal_yields_nothing(): void
    {
        $this->queuePages([$this->page([], 'OK')]);

        $this->assertSame([], $this->walk());
    }

    /**
     * Devices return a bare record rather than a list when a page holds exactly
     * one. Read as a list of fields, that becomes a person per field.
     */
    public function test_a_page_holding_one_record_is_not_read_as_many(): void
    {
        $this->mockClient
            ->shouldReceive('post')
            ->once()
            ->andReturn([
                'UserInfoSearch' => [
                    'responseStatusStrg' => 'OK',
                    'UserInfo' => [
                        'employeeNo' => 'E-1',
                        'name' => 'Solo',
                        'userType' => 'normal',
                        'Valid' => ['enable' => true],
                    ],
                ],
            ]);

        $this->assertSame(['E-1'], $this->walk());
    }

    public function test_a_page_size_below_one_is_refused(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        iterator_to_array($this->persons->all(0));
    }

    /**
     * A caller paginating by hand can hold the session open explicitly, which is
     * what makes `search()` usable for more than a single page.
     */
    public function test_search_accepts_an_explicit_session_id(): void
    {
        $this->queuePages([$this->page(['E-1'], 'MORE')]);

        $this->persons->search(1, 30, 'session-abc');

        $this->assertSame('session-abc', $this->sentConditions[0]['searchID']);
        $this->assertSame(30, $this->sentConditions[0]['searchResultPosition']);
    }
}
