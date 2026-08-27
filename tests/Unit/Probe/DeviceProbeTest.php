<?php

declare(strict_types=1);

namespace Shaykhnazar\HikvisionIsapi\Tests\Unit\Probe;

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use Shaykhnazar\HikvisionIsapi\Authentication\DigestAuthenticator;
use Shaykhnazar\HikvisionIsapi\Client\HikvisionClient;
use Shaykhnazar\HikvisionIsapi\Client\HttpClient;
use Shaykhnazar\HikvisionIsapi\Probe\DeviceProbe;

/**
 * The probe runs on a customer's LAN, against a terminal a business depends on,
 * and produces a file somebody then emails. Three properties have to hold, and
 * the first is the one that would do real harm if it did not:
 *
 *   - no staff member's name, card number or employee number reaches the report;
 *   - nothing it sends changes anything on the device;
 *   - it keeps going when the device refuses, because a refusal is an answer.
 *
 * Run against the real client over a stubbed transport rather than a double.
 * A double would let the probe's own reading of a response be replaced by the
 * test's, which is the thing worth checking.
 */
class DeviceProbeTest extends TestCase
{
    private const NAME = 'Dilnoza Karimova';

    private const CARD = '0009876543';

    private const EMPLOYEE_NO = '1042';

    /**
     * A page of the person list, shaped the way a terminal sends one.
     *
     * @return array<string, mixed>
     */
    private function personPage(int $rows, string $searchId, string $status): array
    {
        $people = [];

        for ($i = 0; $i < $rows; $i++) {
            $people[] = [
                'employeeNo' => self::EMPLOYEE_NO,
                'name' => self::NAME,
                'cardNo' => self::CARD,
                'userType' => 'normal',
            ];
        }

        return ['UserInfoSearch' => [
            'searchID' => $searchId,
            'responseStatusStrg' => $status,
            'numOfMatches' => $rows,
            'totalMatches' => 25,
            'UserInfo' => $people,
        ]];
    }

    /**
     * A JSON response, with the header that makes the client parse it.
     *
     * Without the Content-Type the client hands back `['raw' => …]` and every
     * reading below silently becomes a reading of nothing — which is how the
     * first version of this test passed while measuring the wrong thing.
     *
     * @param  array<string, mixed>  $body
     */
    private function json(array $body, int $status = 200): Response
    {
        return new Response($status, ['Content-Type' => 'application/json'], (string) json_encode($body));
    }

    /**
     * Build a probe whose transport replays these responses in order.
     *
     * @param  list<Response>  $responses
     */
    private function probe(array $responses, bool $withWrongPassword = false): DeviceProbe
    {
        $handler = new MockHandler($responses);

        $client = new HikvisionClient(
            new HttpClient(new Client(['handler' => HandlerStack::create($handler)])),
            new DigestAuthenticator,
            [
                'default' => 'probe',
                'format' => 'json',
                'devices' => ['probe' => [
                    'ip' => '10.0.0.1',
                    'port' => 80,
                    'username' => 'admin',
                    'password' => 'secret',
                    'protocol' => 'http',
                ]],
            ],
        );

        return new DeviceProbe(
            client: $client,
            wrongPasswordClient: $withWrongPassword ? static fn (): HikvisionClient => $client : null,
            now: new \DateTimeImmutable('2026-08-27 12:00:00', new \DateTimeZone('UTC')),
        );
    }

    /** Enough responses to get through every question the probe asks. */
    private function fullRun(): DeviceProbe
    {
        $ok = fn (array $body): Response => $this->json($body);

        return $this->probe([
            // Capabilities — one per endpoint, in declaration order.
            // array_values, because array_map keeps the endpoint names as keys
            // and the replay queue has to be a list.
            ...array_values(array_map(
                static fn (): Response => $ok(['ok' => true]),
                DeviceProbe::CAPABILITY_ENDPOINTS,
            )),
            // Paging: a full page, then a short one that ends the walk.
            $ok($this->personPage(10, 'unused', 'MORE')),
            $ok($this->personPage(3, 'unused', 'OK')),
            // Event total.
            $ok(['AcsEventTotalNum' => ['totalNum' => 412]]),
            // Refusals.
            $this->json(['statusCode' => 4, 'subStatusCode' => 'notSupport', 'errorMsg' => 'no such method'], 404),
            $this->json(['statusCode' => 6, 'subStatusCode' => 'badParameters'], 400),
            $this->json(['statusCode' => 6, 'subStatusCode' => 'badParameters'], 400),
        ]);
    }

    // ------------------------------------------------------- the privacy one

    public function test_no_staff_member_reaches_the_report(): void
    {
        $report = $this->fullRun()->run();

        $serialised = (string) json_encode($report);

        // The transport really did hand it these — the point is that the probe
        // counted the rows and threw the people away.
        $this->assertStringNotContainsString(self::NAME, $serialised);
        $this->assertStringNotContainsString(self::CARD, $serialised);
        $this->assertStringNotContainsString(self::EMPLOYEE_NO, $serialised);
    }

    /**
     * How a row is keyed is worth knowing and safe to keep; who is in it is
     * neither. Kept as its own test because the two live one line apart.
     */
    public function test_field_names_are_kept_and_field_values_are_not(): void
    {
        $report = $this->fullRun()->run();

        $this->assertSame(
            ['employeeNo', 'name', 'cardNo', 'userType'],
            $report['person_paging']['row_fields_seen'],
        );
        $this->assertStringNotContainsString(self::NAME, (string) json_encode($report['person_paging']));
    }

    // ------------------------------------------------------------- read-only

    public function test_every_request_it_makes_is_a_read(): void
    {
        $sent = [];
        $handler = new MockHandler(array_fill(0, 40, $this->json([])));
        $stack = HandlerStack::create($handler);
        $stack->push(static function (callable $next) use (&$sent): callable {
            return static function ($request, $options) use ($next, &$sent) {
                $sent[] = $request->getMethod().' '.$request->getUri()->getPath();

                return $next($request, $options);
            };
        });

        $client = new HikvisionClient(
            new HttpClient(new Client(['handler' => $stack])),
            new DigestAuthenticator,
            ['default' => 'p', 'format' => 'json', 'devices' => ['p' => [
                'ip' => '10.0.0.1', 'port' => 80, 'username' => 'admin',
                'password' => 'secret', 'protocol' => 'http',
            ]]],
        );

        (new DeviceProbe($client))->run();

        $this->assertNotEmpty($sent);

        foreach ($sent as $request) {
            // POST is how ISAPI asks a search question; PUT and DELETE are how
            // it changes something, and neither belongs here.
            $this->assertMatchesRegularExpression(
                '/^(GET|POST) /',
                $request,
                "The probe made a writing request: {$request}",
            );
        }

        $searchOnly = array_filter($sent, static fn (string $r): bool => str_starts_with($r, 'POST '));

        foreach ($searchOnly as $request) {
            $this->assertMatchesRegularExpression(
                '#(Search|TotalNum)$#',
                $request,
                "A POST went somewhere other than a search endpoint: {$request}",
            );
        }
    }

    // -------------------------------------------------------------- refusals

    public function test_a_refusal_is_recorded_with_its_sub_status_code(): void
    {
        $report = $this->fullRun()->run();

        $fault = $report['faults']['unknown_endpoint'];

        $this->assertTrue($fault['refused']);
        $this->assertSame(404, $fault['httpStatus']);
        $this->assertSame(4, $fault['statusCode']);
        $this->assertSame('notSupport', $fault['subStatusCode']);
        $this->assertSame('no such method', $fault['errorMsg']);
    }

    /**
     * A refusal often ignores the format that was asked for, so the XML shape
     * has to be read too — otherwise `subStatusCode` comes back null on exactly
     * the devices this probe exists to learn about.
     */
    public function test_a_refusal_in_xml_is_read_as_well_as_one_in_json(): void
    {
        $parsed = DeviceProbe::parseBody(
            '<?xml version="1.0" encoding="UTF-8"?>'
            .'<ResponseStatus><statusCode>6</statusCode>'
            .'<subStatusCode>notSupport</subStatusCode>'
            .'<statusString>Invalid Operation</statusString></ResponseStatus>'
        );

        $this->assertSame('6', $parsed['statusCode']);
        $this->assertSame('notSupport', $parsed['subStatusCode']);
    }

    public function test_a_provocation_the_device_accepts_is_recorded_too(): void
    {
        $ok = static fn (): Response => new Response(200, [], '{"ok":true}');

        $report = $this->probe([
            ...array_values(array_map($ok, DeviceProbe::CAPABILITY_ENDPOINTS)),
            $ok(), $ok(), $ok(),                      // paging, then event total
            $ok(), $ok(), $ok(),                      // three refusals that were not
        ])->run();

        $this->assertFalse($report['faults']['unknown_endpoint']['refused']);
        $this->assertStringContainsString('accepted', $report['faults']['unknown_endpoint']['note']);
    }

    public function test_a_device_that_refuses_everything_still_produces_a_report(): void
    {
        $report = $this->probe(array_fill(0, 40, new Response(500, [], 'gateway on fire')))->run();

        // Every capability marked unsupported rather than the run dying on the
        // first one: a model that lacks half these endpoints is the normal case,
        // and a probe that stopped there would report nothing about the half it
        // does have.
        foreach ($report['capabilities'] as $name => $capability) {
            $this->assertFalse($capability['supported'], "{$name} should be marked unsupported");
        }

        $this->assertArrayHasKey('not_probed', $report);
    }

    // ---------------------------------------------------------- the B1 question

    public function test_it_says_plainly_when_a_walk_advanced(): void
    {
        $reading = DeviceProbe::readPagingResult([
            ['page' => 1, 'asked_from' => 0, 'rows_returned' => 10, 'searchID_echoed_back' => true],
            ['page' => 2, 'asked_from' => 10, 'rows_returned' => 5, 'searchID_echoed_back' => true],
        ]);

        $this->assertStringContainsString('walk advanced', $reading);
    }

    /**
     * The failure B1 was about: a device that starts a new search each time
     * serves the same first page again, so every page is full and the walk
     * never gets anywhere. Silence here would let it pass for success.
     */
    public function test_it_says_plainly_when_the_search_id_came_back_wrong(): void
    {
        $reading = DeviceProbe::readPagingResult([
            ['page' => 1, 'asked_from' => 0, 'rows_returned' => 10, 'searchID_echoed_back' => true],
            ['page' => 2, 'asked_from' => 10, 'rows_returned' => 10, 'searchID_echoed_back' => false],
        ]);

        $this->assertStringContainsString('did NOT echo', $reading);
    }

    public function test_it_does_not_claim_an_answer_from_too_few_people(): void
    {
        $reading = DeviceProbe::readPagingResult([
            ['page' => 1, 'asked_from' => 0, 'rows_returned' => 3, 'searchID_echoed_back' => true],
        ]);

        $this->assertStringContainsString('Not enough people', $reading);
    }

    // ---------------------------------------------------------- the B2 question

    public function test_it_records_which_key_the_event_total_arrived_under(): void
    {
        $report = $this->fullRun()->run();

        $this->assertTrue($report['event_total']['nested_key_present']);
        $this->assertFalse($report['event_total']['flat_key_present']);
        $this->assertSame('int', $report['event_total']['value_type']);
    }

    // ------------------------------------------------------------- honesty

    public function test_the_report_lists_the_questions_it_could_not_answer(): void
    {
        $report = $this->fullRun()->run();

        // A file with ten answers and no mention of the eleventh question reads
        // as complete, and somebody builds on the gap.
        $this->assertArrayHasKey('capacity_full', $report['not_probed']);
        $this->assertArrayHasKey('unsupported_write', $report['not_probed']);
    }
}
