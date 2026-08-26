<?php

declare(strict_types=1);

namespace Shaykhnazar\HikvisionIsapi\Concerns;

/**
 * One ISAPI search, walked across its pages.
 *
 * ISAPI treats `searchID` as the identity of a *search session*, not of a
 * request. Every page of one search has to carry the same value; change it
 * halfway and the device treats the next call as a brand new search, resets its
 * cursor, and serves the first page again.
 *
 * That failure has no error attached to it. The caller sees plausible records
 * arriving and simply never reaches the end of the list — or loops on the same
 * page until something else stops it. Anything built on top is then reasoning
 * about a roster or an event log it believes is complete and is not.
 *
 * The three rules below are what make a walk correct, and all three matter:
 *
 * 1. One `searchID` for the whole walk.
 * 2. Advance by the number of records the device **actually returned**, never by
 *    page number times page size. A device is free to return fewer than asked
 *    for, and multiplying would then skip whatever it held back.
 * 3. Stop when the device stops saying `MORE` — and stop on an empty page even
 *    if it is still saying `MORE`, because a device that says so while returning
 *    nothing would otherwise spin forever.
 */
trait PagesSearchResults
{
    /** The device is telling us there is at least one more page. */
    private const SEARCH_STATUS_MORE = 'MORE';

    /**
     * A search-session id.
     *
     * Random rather than `time()`: a timestamp changes between two pages
     * requested a second apart, which is precisely the bug this exists to avoid,
     * and two searches started in the same second would collide.
     */
    private static function newSearchId(): string
    {
        return bin2hex(random_bytes(8));
    }

    /**
     * Walk every page of one search, yielding records as they arrive.
     *
     * Yields rather than accumulating, so a wide window or a large roster does
     * not have to be held in memory at once.
     *
     * @template TRecord
     *
     * @param  \Closure(int $position, string $searchId): array{items: list<TRecord>, more: bool}  $fetchPage
     * @return \Generator<int, TRecord>
     */
    private function walkSearch(\Closure $fetchPage): \Generator
    {
        $searchId = self::newSearchId();
        $position = 0;

        do {
            $page = $fetchPage($position, $searchId);

            foreach ($page['items'] as $record) {
                yield $record;
            }

            $returned = count($page['items']);
            $position += $returned;

            $hasMore = $page['more'] && $returned > 0;
        } while ($hasMore);
    }

    /**
     * Whether a search response block says another page is waiting.
     *
     * @param  array<string, mixed>  $block
     */
    private static function saysMore(array $block): bool
    {
        return ($block['responseStatusStrg'] ?? '') === self::SEARCH_STATUS_MORE;
    }
}
