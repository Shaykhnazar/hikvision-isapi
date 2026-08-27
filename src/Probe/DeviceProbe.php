<?php

declare(strict_types=1);

namespace Shaykhnazar\HikvisionIsapi\Probe;

use Shaykhnazar\HikvisionIsapi\Client\HikvisionClient;
use Shaykhnazar\HikvisionIsapi\Exceptions\HikvisionException;

/**
 * Ask a real terminal what it actually does, and write the answers down.
 *
 * Several things in this package are readings that cannot be settled without a
 * device in front of you: how a model reports its capacity, which
 * `subStatusCode` it returns for each kind of refusal, whether it honours a
 * search session across pages, and which key it puts an event total under.
 * Guessing at those produces code that looks finished and is wrong on
 * somebody's wall — so the package's policy has been to leave them open rather
 * than invent them. This is how they get closed.
 *
 * Two properties hold throughout, and both are the point:
 *
 * **Read-only.** Nothing here creates, updates or deletes anything on the
 * device. Even the refusals are provoked with reads, because the machine being
 * probed is usually one a business already depends on.
 *
 * **Nobody's data leaves the building.** The person list is read to learn its
 * *shape* — how many rows came back, what the paging fields said, what a row's
 * keys are called — and the rows themselves are counted and discarded. Field
 * names are kept, field values never are. The report is written to be emailed,
 * and the thing it must not become is a way for staff records to leave a
 * customer's LAN in a support attachment.
 *
 * I/O lives in `bin/hikvision-probe`; this class only asks and reports, so the
 * asking can be tested.
 */
final class DeviceProbe
{
    /** Enough pages to see whether paging works; not enough to walk a big site. */
    public const PAGE_SIZE = 10;

    public const PAGES_TO_WALK = 3;

    /** The report format, so a later reader knows which questions were asked. */
    public const VERSION = 1;

    /**
     * Every read this asks for, and what each one is being asked for.
     *
     * Kept as data rather than a run of method calls so the report can say what
     * was attempted even when the device refuses — a refusal is itself an
     * answer, and on an endpoint like face capabilities it is the answer.
     *
     * @var array<string, array{0: string, 1: string}>
     */
    public const CAPABILITY_ENDPOINTS = [
        'device_info' => ['/ISAPI/System/deviceInfo', 'model, firmware, serial'],
        'access_control' => ['/ISAPI/AccessControl/capabilities', 'what the access-control module offers'],
        'user_info' => ['/ISAPI/AccessControl/UserInfo/capabilities', 'person limits and fields'],
        'user_count' => ['/ISAPI/AccessControl/UserInfo/Count', 'how many people are enrolled now'],
        'card_info' => ['/ISAPI/AccessControl/CardInfo/capabilities', 'card limits and formats'],
        'card_count' => ['/ISAPI/AccessControl/CardInfo/Count', 'how many cards are enrolled now'],
        'face_library' => ['/ISAPI/Intelligent/FDLib/capabilities', 'face library limits'],
        'fingerprint' => ['/ISAPI/AccessControl/FingerPrintCfg/capabilities', 'fingerprint limits'],
        'acs_event' => ['/ISAPI/AccessControl/AcsEvent/capabilities', 'event search fields'],
        'system_status' => ['/ISAPI/System/status', 'uptime and load'],
        'time' => ['/ISAPI/System/time', 'device clock — a drifting clock silently shifts every arrival'],
    ];

    /** @var array<string, mixed> */
    private array $report = [];

    /**
     * @param  HikvisionClient  $client  Authenticated, for everything but the credential probe.
     * @param  (\Closure(): HikvisionClient)|null  $wrongPasswordClient  A client with a deliberately
     *                                                                   wrong password. Null skips
     *                                                                   that one probe.
     * @param  (\Closure(string): void)|null  $progress  Called with a line per answer, for a CLI.
     */
    public function __construct(
        private readonly HikvisionClient $client,
        private readonly ?\Closure $wrongPasswordClient = null,
        private readonly ?\Closure $progress = null,
        private readonly ?\DateTimeImmutable $now = null,
    ) {}

    /**
     * Ask everything, and return what came back.
     *
     * @return array<string, mixed>
     */
    public function run(): array
    {
        $this->report = [
            'probe_version' => self::VERSION,
            'probed_at' => $this->now()->format('c'),
        ];

        $this->probeCapabilities();
        $this->probePersonPaging();
        $this->probeEventTotalShape();
        $this->probeFaults();

        $this->report['not_probed'] = $this->thingsThisCannotAnswer();

        return $this->report;
    }

    // ---------------------------------------------------------------- probes

    /**
     * Read every capability endpoint and keep the response whole.
     *
     * Whole, rather than parsed into the few fields wanted today: the point of
     * running this against a real device is to find out what a real device says,
     * and a parser written before seeing one drops exactly the fields nobody
     * predicted.
     */
    private function probeCapabilities(): void
    {
        $this->say('Capabilities');

        foreach (self::CAPABILITY_ENDPOINTS as $name => [$endpoint, $why]) {
            try {
                $this->report['capabilities'][$name] = [
                    'endpoint' => $endpoint,
                    'asked_for' => $why,
                    'supported' => true,
                    'response' => $this->client->get($endpoint),
                ];
                $this->say("  ok   {$name}");
            } catch (HikvisionException $e) {
                $this->report['capabilities'][$name] = [
                    'endpoint' => $endpoint,
                    'asked_for' => $why,
                    'supported' => false,
                    'failure' => $this->describeFailure($e),
                ];
                $this->say("  no   {$name} — HTTP ".($e->statusCode() ?? 'no response'));
            }
        }
    }

    /**
     * Walk the person list and record what the device says about paging.
     *
     * This is the one that settles B1. The package now sends a single
     * `searchID` for a whole walk, on the reading that Hikvision's `searchID`
     * identifies a search *session*; the old code sent a fresh one per call. If
     * that reading is right, page two continues page one. If it is wrong, page
     * two repeats page one, and the per-page counts say so.
     *
     * No row content is kept — see the note on this class.
     */
    private function probePersonPaging(): void
    {
        $this->say('Person paging');

        $searchId = bin2hex(random_bytes(8));
        $pages = [];
        $position = 0;
        $fieldsSeen = [];

        for ($page = 1; $page <= self::PAGES_TO_WALK; $page++) {
            try {
                $response = $this->client->post('/ISAPI/AccessControl/UserInfo/Search', [
                    'UserInfoSearchCond' => [
                        'searchID' => $searchId,
                        'searchResultPosition' => $position,
                        'maxResults' => self::PAGE_SIZE,
                    ],
                ]);
            } catch (HikvisionException $e) {
                $pages[] = ['page' => $page, 'failure' => $this->describeFailure($e)];
                $this->say("  no   page {$page} — HTTP ".($e->statusCode() ?? 'no response'));
                break;
            }

            $block = is_array($response['UserInfoSearch'] ?? null) ? $response['UserInfoSearch'] : $response;
            $rows = $block['UserInfo'] ?? [];
            $rows = is_array($rows) ? $rows : [];

            // A single row can arrive unwrapped, which would otherwise count as
            // one row per field.
            if ($rows !== [] && !array_is_list($rows)) {
                $rows = [$rows];
            }

            // Identity, not identities: how a row is keyed, never who is in it.
            foreach ($rows as $row) {
                if (is_array($row)) {
                    $fieldsSeen = array_unique([...$fieldsSeen, ...array_keys($row)]);
                }
            }

            $echoed = $block['searchID'] ?? null;

            $pages[] = [
                'page' => $page,
                'asked_from' => $position,
                'asked_for' => self::PAGE_SIZE,
                'rows_returned' => count($rows),
                'numOfMatches' => $block['numOfMatches'] ?? null,
                'totalMatches' => $block['totalMatches'] ?? null,
                'responseStatusStrg' => $block['responseStatusStrg'] ?? null,
                // The whole question, in one field: did the device treat this as
                // a continuation, or start a new search?
                'searchID_echoed_back' => $echoed === $searchId,
            ];

            $this->say(sprintf(
                '  ok   page %d — %d rows, status %s',
                $page,
                count($rows),
                is_scalar($block['responseStatusStrg'] ?? null) ? (string) $block['responseStatusStrg'] : '(none)',
            ));

            $position += count($rows);

            if (count($rows) === 0 || ($block['responseStatusStrg'] ?? null) === 'OK') {
                break;
            }
        }

        $this->report['person_paging'] = [
            'searchID_sent' => 'one session id, reused across every page',
            'page_size' => self::PAGE_SIZE,
            'pages' => $pages,
            'row_fields_seen' => array_values($fieldsSeen),
            'reading' => self::readPagingResult($pages),
        ];
    }

    /**
     * Which key the device puts an event total under.
     *
     * This settles B2. The package reads both the nested and the flat shape and
     * accepts a numeric string, so it no longer matters which one arrives — but
     * knowing which one a given model sends is what a compatibility table is
     * for, and it is one request to find out.
     */
    private function probeEventTotalShape(): void
    {
        $this->say('Event total');

        $to = $this->now();
        $from = $to->sub(new \DateInterval('P7D'));

        try {
            $response = $this->client->post('/ISAPI/AccessControl/AcsEventTotalNum', [
                'AcsEventTotalNumCond' => [
                    'major' => 0,
                    'minor' => 0,
                    'startTime' => $from->format('Y-m-d\TH:i:sP'),
                    'endTime' => $to->format('Y-m-d\TH:i:sP'),
                ],
            ]);

            $nested = is_array($response['AcsEventTotalNum'] ?? null)
                ? ($response['AcsEventTotalNum']['totalNum'] ?? null)
                : null;

            $this->report['event_total'] = [
                'nested_key_present' => isset($response['AcsEventTotalNum']),
                'flat_key_present' => isset($response['totalNum']),
                'value_type' => get_debug_type($nested ?? $response['totalNum'] ?? null),
                'response' => $response,
                'window_days' => 7,
            ];

            $this->say('  ok   '.(isset($response['AcsEventTotalNum'])
                ? 'nested under AcsEventTotalNum'
                : 'flat totalNum'));
        } catch (HikvisionException $e) {
            $this->report['event_total'] = ['failure' => $this->describeFailure($e)];
            $this->say('  no   event total — HTTP '.($e->statusCode() ?? 'no response'));
        }
    }

    /**
     * Make the device refuse things, and write down how it words each refusal.
     *
     * This is what C4 is waiting for. The package classifies failures only by
     * what is certain — whether a response arrived and its HTTP status — because
     * Hikvision's `subStatusCode` strings vary by model and firmware, and a
     * retry policy built on guessed strings would retry the unretryable and give
     * up on the temporary.
     *
     * Every provocation here is a read. Nothing on the device changes.
     */
    private function probeFaults(): void
    {
        $this->say('Refusals');

        if ($this->wrongPasswordClient !== null) {
            $this->recordFault(
                'bad_credentials',
                'the password is wrong — no amount of retrying fixes it',
                function (): void {
                    ($this->wrongPasswordClient)()->get('/ISAPI/System/deviceInfo');
                },
            );
        }

        $this->recordFault(
            'unknown_endpoint',
            'this model does not have that feature',
            fn () => $this->client->get('/ISAPI/AccessControl/ThisEndpointDoesNotExist/capabilities'),
        );

        $this->recordFault(
            'no_such_person',
            'asked about somebody who is not enrolled',
            fn () => $this->client->post('/ISAPI/AccessControl/UserInfo/Search', [
                'UserInfoSearchCond' => [
                    'searchID' => bin2hex(random_bytes(8)),
                    'searchResultPosition' => 0,
                    'maxResults' => 1,
                    // An employee number no site would issue.
                    'EmployeeNoList' => [['employeeNo' => 'PROBE-NOBODY-00000']],
                ],
            ]),
        );

        $this->recordFault(
            'malformed_request',
            'the body is not what the endpoint expects',
            fn () => $this->client->post('/ISAPI/AccessControl/UserInfo/Search', [
                'UserInfoSearchCond' => ['searchResultPosition' => 'not-a-number'],
            ]),
        );
    }

    /**
     * Run one provocation and keep whatever came back.
     *
     * A provocation that *succeeds* is worth recording too: it means this model
     * accepts something another one refuses, which is exactly the sort of
     * difference a compatibility table exists to hold.
     */
    private function recordFault(string $name, string $intent, \Closure $provoke): void
    {
        try {
            $provoke();

            $this->report['faults'][$name] = [
                'intent' => $intent,
                'refused' => false,
                'note' => 'The device accepted this instead of refusing it.',
            ];
            $this->say("  --   {$name} — accepted, not refused");
        } catch (HikvisionException $e) {
            $described = $this->describeFailure($e);

            $this->report['faults'][$name] = [
                'intent' => $intent,
                'refused' => true,
                'exception_class' => (new \ReflectionClass($e))->getShortName(),
                'is_retryable_today' => $e->isRetryable(),
                ...$described,
            ];

            $this->say(sprintf(
                '  ok   %s — HTTP %s, subStatusCode %s',
                $name,
                $e->statusCode() ?? 'no response',
                is_scalar($described['subStatusCode']) ? (string) $described['subStatusCode'] : '(none)',
            ));
        }
    }

    // --------------------------------------------------------------- reading

    /**
     * Pull the parts of a refusal a retry policy could be built on.
     *
     * @return array<string, mixed>
     */
    public function describeFailure(HikvisionException $e): array
    {
        $body = $e->responseBody();
        $parsed = $body === null ? [] : self::parseBody($body);

        return [
            'httpStatus' => $e->statusCode(),
            'statusCode' => $parsed['statusCode'] ?? null,
            'subStatusCode' => $parsed['subStatusCode'] ?? null,
            'errorCode' => $parsed['errorCode'] ?? null,
            'errorMsg' => $parsed['errorMsg'] ?? $parsed['statusString'] ?? null,
            // Kept whole and capped: a refusal body carries codes and messages,
            // never enrolment data, and a shape nobody predicted is the reason
            // for running this at all.
            'raw_body' => $body === null ? null : mb_substr($body, 0, 2000),
        ];
    }

    /**
     * Read a refusal body, whichever of the two shapes it arrives in.
     *
     * Hikvision answers JSON or XML depending on the endpoint and the `format`
     * parameter, and a refusal often ignores the format that was asked for.
     *
     * @return array<string, mixed>
     */
    public static function parseBody(string $body): array
    {
        $json = json_decode($body, true);

        if (is_array($json)) {
            return $json;
        }

        $previous = libxml_use_internal_errors(true);
        $xml = simplexml_load_string($body);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        if ($xml === false) {
            return [];
        }

        $encoded = json_encode($xml);
        $decoded = $encoded === false ? null : json_decode($encoded, true);

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * Say in one sentence what the paging pages amount to.
     *
     * A report nobody can read is a report nobody acts on, and this is the
     * finding most likely to matter.
     *
     * @param  list<array<string, mixed>>  $pages
     */
    public static function readPagingResult(array $pages): string
    {
        $walked = array_values(array_filter($pages, fn (array $p): bool => isset($p['rows_returned'])));

        if (count($walked) < 2) {
            return 'Not enough people enrolled to test paging — it needs more than '
                .self::PAGE_SIZE.' to say anything either way.';
        }

        $echoed = array_filter($walked, fn (array $p): bool => $p['searchID_echoed_back'] === true);

        if (count($echoed) !== count($walked)) {
            return 'The device did NOT echo the searchID back on every page. Treat a full '
                .'roster read as unverified on this model and check the per-page counts.';
        }

        // The failure this is really looking for: a device that restarts the
        // search each time serves the same first page again, so every page is
        // full and the walk never advances.
        $advanced = count(array_unique(array_map(
            fn (array $p): int => (int) $p['asked_from'],
            $walked,
        ))) === count($walked);

        if (!$advanced) {
            return 'The searchID came back, but the walk did not advance — the device is '
                .'serving the same page again.';
        }

        return 'The device echoed the same searchID on every page and the walk advanced, '
            .'which is what a search session should do.';
    }

    /**
     * What still cannot be answered, and why.
     *
     * Written into the report on purpose. A file listing ten answers and
     * silently omitting the eleventh question reads as complete, and somebody
     * will later build on a gap they were never told about.
     *
     * @return array<string, string>
     */
    public static function thingsThisCannotAnswer(): array
    {
        return [
            'capacity_full' => 'What a device says when its person or face storage is full. '
                .'Provoking it means filling the device, which is a write and not something to '
                .'do to a terminal a business depends on. It needs a spare unit.',
            'unsupported_write' => 'How a model refuses a write it does not support — adding a '
                .'face to a card-only terminal, say. Every probe here is a read.',
            'long_run_behaviour' => 'Whether a search session survives minutes of paging on a '
                .'large site. This walks '.self::PAGES_TO_WALK.' pages of '.self::PAGE_SIZE.'.',
        ];
    }

    private function now(): \DateTimeImmutable
    {
        return $this->now ?? new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
    }

    private function say(string $line): void
    {
        if ($this->progress !== null) {
            ($this->progress)($line);
        }
    }
}
