<?php

declare(strict_types=1);

namespace Shaykhnazar\HikvisionIsapi\Tests\Unit\Services;

use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\TestCase;
use Shaykhnazar\HikvisionIsapi\Client\HikvisionClient;
use Shaykhnazar\HikvisionIsapi\DTOs\Card;
use Shaykhnazar\HikvisionIsapi\Services\CardService;

/**
 * The same walk, over cards.
 *
 * Kept as its own test rather than trusting the shared trait: the trait decides
 * how to page, but each service still has to hand it the right block of the
 * response, and getting that wrong is silent in exactly the same way.
 */
class CardSearchPagingTest extends TestCase
{
    /** @var HikvisionClient&MockInterface */
    private HikvisionClient $mockClient;

    private CardService $cards;

    /** @var list<array<string, mixed>> */
    private array $sentConditions = [];

    protected function setUp(): void
    {
        parent::setUp();

        /** @var HikvisionClient&MockInterface $client */
        $client = Mockery::mock(HikvisionClient::class);

        $this->mockClient = $client;
        $this->cards = new CardService($this->mockClient);
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
            ->with('/ISAPI/AccessControl/CardInfo/Search', Mockery::type('array'))
            ->times(count($pages))
            ->andReturnUsing(function (string $endpoint, array $data) use (&$pages) {
                $this->sentConditions[] = $data['CardInfoSearchCond'];

                return array_shift($pages);
            });
    }

    /**
     * @param  list<string>  $cardNos
     * @return array<string, mixed>
     */
    private function page(array $cardNos, string $status): array
    {
        return [
            'CardInfoSearch' => [
                'responseStatusStrg' => $status,
                'CardInfo' => array_map(static fn (string $no): array => [
                    'employeeNo' => 'E-'.$no,
                    'cardNo' => $no,
                ], $cardNos),
            ],
        ];
    }

    /** @return list<string> */
    private function walk(int $pageSize = 2): array
    {
        return array_map(
            static fn (Card $card): string => $card->cardNo,
            iterator_to_array($this->cards->all($pageSize), false),
        );
    }

    public function test_one_walk_is_one_search_session(): void
    {
        $this->queuePages([
            $this->page(['1', '2'], 'MORE'),
            $this->page(['3'], 'OK'),
        ]);

        $this->walk();

        $ids = array_column($this->sentConditions, 'searchID');

        $this->assertCount(1, array_unique($ids));
        $this->assertFalse(is_numeric($ids[0]), 'a clock-derived id changes mid-walk');
    }

    public function test_it_follows_pages_until_the_device_stops_saying_more(): void
    {
        $this->queuePages([
            $this->page(['1', '2'], 'MORE'),
            $this->page(['3'], 'OK'),
        ]);

        $this->assertSame(['1', '2', '3'], $this->walk());
    }

    public function test_an_empty_page_ends_the_walk_even_when_the_device_says_more(): void
    {
        $this->queuePages([
            $this->page(['1'], 'MORE'),
            $this->page([], 'MORE'),
        ]);

        $this->assertSame(['1'], $this->walk());
    }

    public function test_a_filter_is_carried_on_every_page(): void
    {
        $this->queuePages([
            $this->page(['1'], 'MORE'),
            $this->page(['2'], 'OK'),
        ]);

        iterator_to_array($this->cards->all(1, employeeNo: 'EMP-7'), false);

        $this->assertSame(['EMP-7', 'EMP-7'], array_column($this->sentConditions, 'employeeNo'));
    }

    /**
     * An empty filter must not be sent as a condition: a device asked for cards
     * belonging to employee "" may answer with none rather than with all.
     */
    public function test_an_absent_filter_is_not_sent_at_all(): void
    {
        $this->queuePages([$this->page(['1'], 'OK')]);

        $this->walk();

        $this->assertArrayNotHasKey('employeeNo', $this->sentConditions[0]);
        $this->assertArrayNotHasKey('cardNo', $this->sentConditions[0]);
    }

    public function test_a_page_size_below_one_is_refused(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        iterator_to_array($this->cards->all(0));
    }
}
