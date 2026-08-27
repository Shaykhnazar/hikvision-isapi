<?php

declare(strict_types=1);

namespace Shaykhnazar\HikvisionIsapi\Tests\Unit\Services;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Request;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shaykhnazar\HikvisionIsapi\Authentication\DigestAuthenticator;
use Shaykhnazar\HikvisionIsapi\Client\HikvisionClient;
use Shaykhnazar\HikvisionIsapi\Client\HttpClient;
use Shaykhnazar\HikvisionIsapi\Services\FaceService;

/**
 * A face must not survive an exception.
 *
 * PHP records call arguments in a stack trace unless told otherwise, and the
 * setting that tells it otherwise — `zend.exception_ignore_args` — has a
 * compiled default of 0 and is not set at all by the official `php:*-alpine`
 * images this ships on. So without `#[\SensitiveParameter]` a single failed
 * upload puts an entire base64 face into `getTrace()`, and the first person to
 * log a trace publishes it.
 *
 * These tests assert the redaction directly rather than trusting the INI,
 * because trusting the INI is the mistake: it makes a privacy property depend
 * on how somebody configured the machine.
 *
 * They run against the real client over a stubbed transport. An earlier version
 * used a Mockery double and was worthless — Mockery's generated class drops
 * parameter attributes, and worse, it *records the arguments it received*, so
 * dumping the trace walked into the mock's own bookkeeping and found the face
 * there. It failed while the production path was clean.
 *
 * IMPORTANT, and the reason these tests are scoped to this package's frames:
 * the attribute is necessary and **not sufficient**. The payload continues into
 * Guzzle, whose `$options` array carries the body and the `auth` credentials,
 * and nothing here can annotate Guzzle's parameters. Below this package only
 * `zend.exception_ignore_args=1` redacts them — so an application shipping this
 * SDK has to set it. The agent's image does, and asserts so in its own tests.
 */
class FaceRedactionTest extends TestCase
{
    private const FACE = 'SECRET-FACE-BASE64-PAYLOAD';

    private function service(): FaceService
    {
        $guzzle = new Client([
            'handler' => HandlerStack::create(new MockHandler([
                new ConnectException('terminal unreachable', new Request('POST', '/')),
            ])),
        ]);

        return new FaceService(new HikvisionClient(
            new HttpClient($guzzle),
            new DigestAuthenticator,
            [
                'default' => 'dev_1',
                'format' => 'json',
                'devices' => ['dev_1' => [
                    'ip' => '192.0.2.1',
                    'port' => 80,
                    'username' => 'admin',
                    'password' => 'SECRET-DEVICE-PASSWORD',
                    'protocol' => 'http',
                ]],
            ],
        ));
    }

    /**
     * Every argument this package's own frames record, flattened.
     *
     * Scoped to our own classes on purpose. The payload does not stop at the
     * edge of this package: it continues into Guzzle, whose `$options` array
     * carries both the request body and the `auth` credentials, and no attribute
     * of ours reaches those parameters. Below this package the only thing that
     * redacts them is `zend.exception_ignore_args`, which is why the agent image
     * must set it — see the note on the class.
     *
     * Deliberately shallow as well: it walks arrays but never descends into
     * objects. A recursive dump drags in whatever those objects happen to
     * reference and reports a leak that is not this code's, which is exactly how
     * the first version of this test fooled itself.
     */
    private function recordedArguments(\Throwable $e): string
    {
        $flatten = static function (mixed $value) use (&$flatten): string {
            if (is_array($value)) {
                return implode(' ', array_map($flatten, $value));
            }

            return is_scalar($value) ? (string) $value : '';
        };

        $seen = [];

        foreach ($e->getTrace() as $frame) {
            $class = $frame['class'] ?? '';

            if (!str_starts_with($class, 'Shaykhnazar\\HikvisionIsapi\\')) {
                continue;
            }

            $seen[] = $flatten($frame['args'] ?? []);
        }

        return implode(' ', $seen);
    }

    /**
     * @param  \Closure(FaceService): mixed  $call
     */
    private function assertNothingSecretWasRecorded(\Closure $call): void
    {
        try {
            $call($this->service());
            $this->fail('expected the call to throw');
        } catch (\Throwable $e) {
            $recorded = $this->recordedArguments($e);

            $this->assertStringNotContainsString(
                self::FACE,
                $recorded,
                'the face reached the stack trace, where anything logging it would publish it',
            );

            $this->assertStringNotContainsString(
                'SECRET-DEVICE-PASSWORD',
                $recorded,
                'the device password reached the stack trace',
            );
        }
    }

    public function test_upload_face_records_neither_the_image_nor_the_password(): void
    {
        $this->assertNothingSecretWasRecorded(
            fn (FaceService $faces) => $faces->uploadFace('EMP001', self::FACE),
        );
    }

    public function test_upload_face_data_record_records_neither(): void
    {
        $this->assertNothingSecretWasRecorded(
            fn (FaceService $faces) => $faces->uploadFaceDataRecord(1, 'EMP001', self::FACE),
        );
    }

    public function test_setup_face_data_records_neither(): void
    {
        $this->assertNothingSecretWasRecorded(
            fn (FaceService $faces) => $faces->setupFaceData(1, 'EMP001', self::FACE),
        );
    }

    public function test_modify_face_record_records_neither(): void
    {
        $this->assertNothingSecretWasRecorded(
            fn (FaceService $faces) => $faces->modifyFaceRecord(1, 'EMP001', self::FACE),
        );
    }

    /**
     * The invariant, stated structurally: these parameters must carry the
     * attribute. A behavioural test alone would quietly start passing for the
     * wrong reason on a machine whose INI hides arguments anyway.
     *
     * @return list<array{class-string, string, string}>
     */
    public static function sensitiveParameters(): array
    {
        return [
            [FaceService::class, 'uploadFace', 'faceImageBase64'],
            [FaceService::class, 'uploadFaceDataRecord', 'imageContent'],
            [FaceService::class, 'setupFaceData', 'imageContent'],
            [FaceService::class, 'modifyFaceRecord', 'imageContent'],
            [HikvisionClient::class, 'post', 'data'],
            [HikvisionClient::class, 'put', 'data'],
            [HikvisionClient::class, 'putXml', 'data'],
            [HikvisionClient::class, 'postMultipart', 'multipart'],
            [HikvisionClient::class, 'putMultipart', 'multipart'],
            [HttpClient::class, 'post', 'data'],
            [HttpClient::class, 'put', 'data'],
            [HttpClient::class, 'postMultipart', 'multipart'],
            [HttpClient::class, 'putMultipart', 'multipart'],
            [DigestAuthenticator::class, 'buildAuthOptions', 'password'],
        ];
    }

    /**
     * @param  class-string  $class
     */
    #[DataProvider('sensitiveParameters')]
    public function test_the_parameter_is_marked_sensitive(string $class, string $method, string $parameter): void
    {
        $found = null;

        foreach ((new \ReflectionMethod($class, $method))->getParameters() as $candidate) {
            if ($candidate->getName() === $parameter) {
                $found = $candidate;
                break;
            }
        }

        $this->assertNotNull($found, "{$class}::{$method}() has no \${$parameter}");
        $this->assertNotEmpty(
            $found->getAttributes(\SensitiveParameter::class),
            "{$class}::{$method}(\${$parameter}) must be marked #[\\SensitiveParameter]",
        );
    }

    /**
     * The guard rail for the guard rail: proves an unmarked parameter really is
     * recorded, so the tests above are not passing merely because this PHP is
     * configured to hide arguments.
     */
    public function test_an_unmarked_parameter_really_does_leak(): void
    {
        if (ini_get('zend.exception_ignore_args')) {
            $this->markTestSkipped('this PHP hides all arguments; the leak cannot be demonstrated here');
        }

        $leaky = static function (string $employeeNo, string $face): void {
            throw new \RuntimeException('device said no');
        };

        try {
            $leaky('EMP001', self::FACE);
        } catch (\RuntimeException $e) {
            // Read straight off the frame rather than through
            // recordedArguments(), which filters to this package's classes. That
            // filter happens to admit this test's own frames — same namespace
            // prefix — and a guard rail that passes by coincidence is not one.
            $recorded = '';

            foreach ($e->getTrace() as $frame) {
                foreach ($frame['args'] ?? [] as $argument) {
                    $recorded .= is_scalar($argument) ? (string) $argument : '';
                }
            }

            $this->assertStringContainsString(
                self::FACE,
                $recorded,
                'without the attribute the value is recorded — which is why the attribute is needed',
            );
        }
    }
}
