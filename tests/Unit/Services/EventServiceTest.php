<?php

declare(strict_types=1);

namespace Shaykhnazar\HikvisionIsapi\Tests\Unit\Services;

use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\TestCase;
use Shaykhnazar\HikvisionIsapi\Client\HikvisionClient;
use Shaykhnazar\HikvisionIsapi\Services\EventService;

class EventServiceTest extends TestCase
{
    /** @var HikvisionClient&MockInterface */
    private HikvisionClient $mockClient;

    private EventService $eventService;

    /** @var list<array<string, mixed>> */
    private array $sentConditions = [];

    protected function setUp(): void
    {
        parent::setUp();

        /** @var HikvisionClient&MockInterface $client */
        $client = Mockery::mock(HikvisionClient::class);

        $this->mockClient = $client;
        $this->eventService = new EventService($this->mockClient);
        $this->sentConditions = [];
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /**
     * Queue the given pages as consecutive responses, recording what was sent.
     *
     * @param  list<array<string, mixed>>  $pages
     */
    private function queuePages(array $pages): void
    {
        $this->mockClient
            ->shouldReceive('post')
            ->with('/ISAPI/AccessControl/AcsEvent', Mockery::type('array'))
            ->times(count($pages))
            ->andReturnUsing(function (string $endpoint, array $data) use (&$pages) {
                $this->sentConditions[] = $data['AcsEventCond'];

                return array_shift($pages);
            });
    }

    /**
     * @param  list<array<string, mixed>>  $events
     * @return array<string, mixed>
     */
    private function page(array $events, string $status): array
    {
        return ['AcsEvent' => ['responseStatusStrg' => $status, 'InfoList' => $events]];
    }

    public function test_between_yields_events_from_a_single_page(): void
    {
        $this->queuePages([
            $this->page([['employeeNoString' => 'EMP001'], ['employeeNoString' => 'EMP002']], 'OK'),
        ]);

        $events = iterator_to_array($this->eventService->between(
            new \DateTimeImmutable('2026-08-25T08:00:00+05:00'),
            new \DateTimeImmutable('2026-08-25T09:00:00+05:00'),
        ));

        $this->assertCount(2, $events);
        $this->assertSame('EMP001', $events[0]['employeeNoString']);
    }

    public function test_between_follows_pages_while_the_device_reports_more(): void
    {
        $this->queuePages([
            $this->page([['id' => 1], ['id' => 2]], 'MORE'),
            $this->page([['id' => 3]], 'OK'),
        ]);

        $events = iterator_to_array($this->eventService->between(
            new \DateTimeImmutable('2026-08-25T08:00:00+05:00'),
            new \DateTimeImmutable('2026-08-25T09:00:00+05:00'),
            pageSize: 2,
        ));

        $this->assertSame([1, 2, 3], array_column($events, 'id'));
    }

    public function test_every_page_of_one_search_reuses_the_same_search_id(): void
    {
        $this->queuePages([
            $this->page([['id' => 1]], 'MORE'),
            $this->page([['id' => 2]], 'OK'),
        ]);

        iterator_to_array($this->eventService->between(
            new \DateTimeImmutable('2026-08-25T08:00:00+05:00'),
            new \DateTimeImmutable('2026-08-25T09:00:00+05:00'),
            pageSize: 1,
        ));

        $this->assertCount(2, $this->sentConditions);
        $this->assertSame(
            $this->sentConditions[0]['searchID'],
            $this->sentConditions[1]['searchID'],
        );
        $this->assertNotSame('', $this->sentConditions[0]['searchID']);
    }

    public function test_position_advances_by_the_number_of_records_returned(): void
    {
        $this->queuePages([
            // The device returns fewer records than asked for, then more remain.
            $this->page([['id' => 1], ['id' => 2]], 'MORE'),
            $this->page([['id' => 3]], 'OK'),
        ]);

        iterator_to_array($this->eventService->between(
            new \DateTimeImmutable('2026-08-25T08:00:00+05:00'),
            new \DateTimeImmutable('2026-08-25T09:00:00+05:00'),
            pageSize: 5,
        ));

        $this->assertSame(0, $this->sentConditions[0]['searchResultPosition']);
        $this->assertSame(2, $this->sentConditions[1]['searchResultPosition']);
    }

    public function test_time_window_is_sent_as_iso8601_with_offset(): void
    {
        $this->queuePages([$this->page([], 'NO MATCH')]);

        iterator_to_array($this->eventService->between(
            new \DateTimeImmutable('2026-08-25T08:00:00+05:00'),
            new \DateTimeImmutable('2026-08-25T09:30:00+05:00'),
        ));

        $this->assertSame('2026-08-25T08:00:00+05:00', $this->sentConditions[0]['startTime']);
        $this->assertSame('2026-08-25T09:30:00+05:00', $this->sentConditions[0]['endTime']);
    }

    public function test_extra_conditions_are_merged_but_cannot_override_paging(): void
    {
        $this->queuePages([$this->page([], 'OK')]);

        iterator_to_array($this->eventService->between(
            new \DateTimeImmutable('2026-08-25T08:00:00+05:00'),
            new \DateTimeImmutable('2026-08-25T09:00:00+05:00'),
            ['major' => 5, 'searchResultPosition' => 999],
            pageSize: 10,
        ));

        $this->assertSame(5, $this->sentConditions[0]['major']);
        $this->assertSame(0, $this->sentConditions[0]['searchResultPosition']);
        $this->assertSame(10, $this->sentConditions[0]['maxResults']);
    }

    public function test_a_single_record_page_is_normalized_to_a_list(): void
    {
        // Devices return a bare record rather than a list when a page holds one event.
        $this->queuePages([
            ['AcsEvent' => ['responseStatusStrg' => 'OK', 'InfoList' => ['employeeNoString' => 'EMP001']]],
        ]);

        $events = iterator_to_array($this->eventService->between(
            new \DateTimeImmutable('2026-08-25T08:00:00+05:00'),
            new \DateTimeImmutable('2026-08-25T09:00:00+05:00'),
        ));

        $this->assertSame([['employeeNoString' => 'EMP001']], $events);
    }

    public function test_empty_window_yields_nothing(): void
    {
        $this->queuePages([$this->page([], 'NO MATCH')]);

        $events = iterator_to_array($this->eventService->between(
            new \DateTimeImmutable('2026-08-25T08:00:00+05:00'),
            new \DateTimeImmutable('2026-08-25T09:00:00+05:00'),
        ));

        $this->assertSame([], $events);
    }

    public function test_more_status_with_an_empty_page_stops_instead_of_looping_forever(): void
    {
        $this->queuePages([$this->page([], 'MORE')]);

        $events = iterator_to_array($this->eventService->between(
            new \DateTimeImmutable('2026-08-25T08:00:00+05:00'),
            new \DateTimeImmutable('2026-08-25T09:00:00+05:00'),
        ));

        $this->assertSame([], $events);
    }

    public function test_reversed_window_is_rejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('from must not be later than to');

        iterator_to_array($this->eventService->between(
            new \DateTimeImmutable('2026-08-25T09:00:00+05:00'),
            new \DateTimeImmutable('2026-08-25T08:00:00+05:00'),
        ));
    }

    public function test_non_positive_page_size_is_rejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('pageSize must be at least 1');

        iterator_to_array($this->eventService->between(
            new \DateTimeImmutable('2026-08-25T08:00:00+05:00'),
            new \DateTimeImmutable('2026-08-25T09:00:00+05:00'),
            pageSize: 0,
        ));
    }

    public function test_search_generates_a_search_id_when_none_is_given(): void
    {
        $this->queuePages([$this->page([], 'OK')]);

        $this->eventService->search(['major' => 5]);

        $this->assertNotSame('', $this->sentConditions[0]['searchID']);
    }

    public function test_search_uses_an_explicit_search_id_when_paginating(): void
    {
        $this->queuePages([$this->page([], 'MORE'), $this->page([], 'OK')]);

        $this->eventService->search([], 0, 30, 'fixed-search-id');
        $this->eventService->search([], 1, 30, 'fixed-search-id');

        $this->assertSame('fixed-search-id', $this->sentConditions[0]['searchID']);
        $this->assertSame('fixed-search-id', $this->sentConditions[1]['searchID']);
        $this->assertSame(0, $this->sentConditions[0]['searchResultPosition']);
        $this->assertSame(30, $this->sentConditions[1]['searchResultPosition']);
    }

    /**
     * B2: the count endpoint answers under `AcsEventTotalNum`, and this client
     * only ever looked at the top level — so on a device that follows the
     * documentation it returned zero for everything, silently, with nothing to
     * separate that from "no events".
     *
     * A daily audit comparing a device count against the cloud's own would have
     * reported total event loss, every day, on every terminal.
     */
    public function test_count_reads_the_documented_nested_shape(): void
    {
        $this->mockClient
            ->shouldReceive('post')
            ->with('/ISAPI/AccessControl/AcsEventTotalNum', Mockery::type('array'))
            ->once()
            ->andReturn(['AcsEventTotalNum' => ['totalNum' => 137]]);

        $this->assertSame(137, $this->eventService->count([]));
    }

    /**
     * Some firmware answers flat. Reading both is what makes it unnecessary to
     * know which — the shape is one of the things only a real terminal settles.
     */
    public function test_count_still_reads_a_flat_shape(): void
    {
        $this->mockClient
            ->shouldReceive('post')
            ->once()
            ->andReturn(['totalNum' => 42]);

        $this->assertSame(42, $this->eventService->count([]));
    }

    public function test_count_is_zero_when_the_device_says_nothing_useful(): void
    {
        $this->mockClient
            ->shouldReceive('post')
            ->once()
            ->andReturn(['AcsEventTotalNum' => ['responseStatusStrg' => 'OK']]);

        $this->assertSame(0, $this->eventService->count([]));
    }

    /**
     * Devices answer numbers as strings often enough that treating one as zero
     * would reproduce the original bug by a different route.
     */
    public function test_count_accepts_a_numeric_string(): void
    {
        $this->mockClient
            ->shouldReceive('post')
            ->once()
            ->andReturn(['AcsEventTotalNum' => ['totalNum' => '58']]);

        $this->assertSame(58, $this->eventService->count([]));
    }
}
