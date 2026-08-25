<?php

declare(strict_types=1);

namespace Shaykhnazar\HikvisionIsapi\Tests\Unit\Client;

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use Shaykhnazar\HikvisionIsapi\Client\HttpClient;
use Shaykhnazar\HikvisionIsapi\Exceptions\HikvisionException;

class HttpClientTest extends TestCase
{
    /** @var list<array<string, mixed>> */
    private array $history = [];

    /**
     * @param  list<Response|\Throwable>  $queue
     */
    private function makeClient(array $queue): HttpClient
    {
        $this->history = [];

        $stack = HandlerStack::create(new MockHandler($queue));
        $stack->push(Middleware::history($this->history));

        return new HttpClient(new Client(['handler' => $stack]));
    }

    private function lastRequestBody(): string
    {
        /** @var Request $request */
        $request = $this->history[count($this->history) - 1]['request'];

        return (string) $request->getBody();
    }

    public function test_json_response_is_decoded(): void
    {
        $client = $this->makeClient([
            new Response(200, ['Content-Type' => 'application/json'], '{"DeviceInfo":{"model":"DS-K1T671M"}}'),
        ]);

        $result = $client->get('http://device.local/ISAPI/System/deviceInfo');

        $this->assertSame(['DeviceInfo' => ['model' => 'DS-K1T671M']], $result);
    }

    public function test_xml_response_is_converted_to_array(): void
    {
        $xml = '<?xml version="1.0" encoding="UTF-8"?><DeviceInfo><model>DS-K1T671M</model></DeviceInfo>';

        $client = $this->makeClient([
            new Response(200, ['Content-Type' => 'application/xml'], $xml),
        ]);

        $result = $client->get('http://device.local/ISAPI/System/deviceInfo');

        $this->assertSame(['model' => 'DS-K1T671M'], $result);
    }

    public function test_text_xml_content_type_is_also_parsed(): void
    {
        $client = $this->makeClient([
            new Response(200, ['Content-Type' => 'text/xml'], '<ResponseStatus><statusCode>1</statusCode></ResponseStatus>'),
        ]);

        $result = $client->get('http://device.local/ISAPI/AccessControl/UserInfo/Delete');

        $this->assertSame(['statusCode' => '1'], $result);
    }

    public function test_malformed_xml_falls_back_to_raw_body_without_warnings(): void
    {
        $broken = '<ResponseStatus><statusCode>1</statusCod';

        $client = $this->makeClient([
            new Response(200, ['Content-Type' => 'application/xml'], $broken),
        ]);

        $result = $client->get('http://device.local/ISAPI/System/deviceInfo');

        $this->assertSame(['raw' => $broken], $result);
    }

    public function test_unknown_content_type_returns_raw_body(): void
    {
        $client = $this->makeClient([
            new Response(200, ['Content-Type' => 'text/plain'], 'OK'),
        ]);

        $result = $client->get('http://device.local/ISAPI/System/deviceInfo');

        $this->assertSame(['raw' => 'OK'], $result);
    }

    public function test_post_sends_json_body_by_default(): void
    {
        $client = $this->makeClient([
            new Response(200, ['Content-Type' => 'application/json'], '{}'),
        ]);

        $client->post('http://device.local/ISAPI/AccessControl/UserInfo/Record', [
            'UserInfo' => ['employeeNo' => 'EMP001'],
        ]);

        $this->assertSame('{"UserInfo":{"employeeNo":"EMP001"}}', $this->lastRequestBody());
    }

    public function test_post_sends_xml_body_when_format_is_xml(): void
    {
        $client = $this->makeClient([
            new Response(200, ['Content-Type' => 'application/xml'], '<ResponseStatus/>'),
        ]);

        $client->post('http://device.local/ISAPI/AccessControl/UserInfo/Record', [
            'UserInfo' => ['employeeNo' => 'EMP001', 'valid' => true],
        ], ['_format' => 'xml']);

        $body = $this->lastRequestBody();

        $this->assertStringContainsString('<UserInfo', $body);
        $this->assertStringContainsString('<employeeNo>EMP001</employeeNo>', $body);
        $this->assertStringContainsString('<valid>true</valid>', $body);
    }

    public function test_format_marker_is_not_forwarded_to_guzzle(): void
    {
        $client = $this->makeClient([
            new Response(200, ['Content-Type' => 'application/json'], '{}'),
        ]);

        $client->post('http://device.local/ISAPI/AccessControl/UserInfo/Record', ['UserInfo' => []], ['_format' => 'json']);

        /** @var Request $request */
        $request = $this->history[0]['request'];

        $this->assertFalse($request->hasHeader('_format'));
    }

    public function test_multipart_request_is_sent(): void
    {
        $client = $this->makeClient([
            new Response(200, ['Content-Type' => 'application/json'], '{}'),
        ]);

        $client->postMultipart('http://device.local/ISAPI/Intelligent/FDLib/FDSetUp', [
            ['name' => 'FaceDataRecord', 'contents' => '{"faceLibType":"blackFD"}'],
        ]);

        /** @var Request $request */
        $request = $this->history[0]['request'];

        $this->assertStringContainsString('multipart/form-data', $request->getHeaderLine('Content-Type'));
    }

    public function test_http_error_is_wrapped_in_hikvision_exception(): void
    {
        $client = $this->makeClient([
            new Response(401, ['Content-Type' => 'application/xml'], '<ResponseStatus><statusCode>4</statusCode></ResponseStatus>'),
        ]);

        $this->expectException(HikvisionException::class);
        $this->expectExceptionMessage('HTTP request failed');

        $client->get('http://device.local/ISAPI/System/deviceInfo');
    }

    public function test_error_message_includes_device_response_body(): void
    {
        $client = $this->makeClient([
            new Response(403, ['Content-Type' => 'application/xml'], '<ResponseStatus><subStatusCode>notSupport</subStatusCode></ResponseStatus>'),
        ]);

        try {
            $client->get('http://device.local/ISAPI/AccessControl/FingerPrint/Capabilities');
            $this->fail('Expected HikvisionException was not thrown.');
        } catch (HikvisionException $e) {
            $this->assertStringContainsString('notSupport', $e->getMessage());
            $this->assertSame(403, $e->getCode());
        }
    }
}
