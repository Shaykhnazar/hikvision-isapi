<?php

declare(strict_types=1);

namespace Shaykhnazar\HikvisionIsapi\Tests\Unit\Client;

use PHPUnit\Framework\TestCase;
use Shaykhnazar\HikvisionIsapi\Authentication\DigestAuthenticator;
use Shaykhnazar\HikvisionIsapi\Client\HikvisionClient;
use Shaykhnazar\HikvisionIsapi\Exceptions\HikvisionException;
use Shaykhnazar\HikvisionIsapi\Tests\Support\RecordingHttpClient;

class HikvisionClientTest extends TestCase
{
    private RecordingHttpClient $http;

    protected function setUp(): void
    {
        parent::setUp();

        $this->http = new RecordingHttpClient;
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function config(array $overrides = [], string $format = 'json'): array
    {
        return [
            'default' => 'entrance',
            'format' => $format,
            'devices' => [
                'entrance' => array_merge([
                    'ip' => '192.168.1.10',
                    'port' => 8080,
                    'username' => 'admin',
                    'password' => 'secret',
                    'protocol' => 'https',
                    'timeout' => 15,
                    'verify_ssl' => true,
                ], $overrides),
            ],
        ];
    }

    private function client(string $format = 'json'): HikvisionClient
    {
        return new HikvisionClient($this->http, new DigestAuthenticator, $this->config(format: $format));
    }

    public function test_base_url_is_built_from_protocol_ip_and_port(): void
    {
        $this->client()->get('/ISAPI/System/deviceInfo');

        $this->assertStringStartsWith('https://192.168.1.10:8080/ISAPI/System/deviceInfo', $this->http->uri);
    }

    public function test_format_is_appended_as_query_parameter(): void
    {
        $this->client()->get('/ISAPI/System/deviceInfo');

        $this->assertStringContainsString('format=json', $this->http->uri);
    }

    public function test_explicit_query_format_is_not_overridden(): void
    {
        $this->client()->get('/ISAPI/System/deviceInfo', ['format' => 'xml']);

        $this->assertStringContainsString('format=xml', $this->http->uri);
        $this->assertStringNotContainsString('format=json', $this->http->uri);
    }

    public function test_auth_timeout_and_ssl_options_are_forwarded(): void
    {
        $this->client()->get('/ISAPI/System/deviceInfo');

        $this->assertSame(['admin', 'secret', 'digest'], $this->http->options['auth']);
        $this->assertSame(15, $this->http->options['timeout']);
        $this->assertTrue($this->http->options['verify']);
    }

    public function test_json_format_sets_json_headers(): void
    {
        $this->client()->post('/ISAPI/AccessControl/UserInfo/Record', ['UserInfo' => []]);

        $this->assertSame('application/json', $this->http->options['headers']['Content-Type']);
        $this->assertSame('application/json', $this->http->options['headers']['Accept']);
        $this->assertSame('json', $this->http->options['_format']);
    }

    public function test_xml_format_sets_xml_headers(): void
    {
        $this->client('xml')->post('/ISAPI/AccessControl/UserInfo/Record', ['UserInfo' => []]);

        $this->assertSame('application/xml', $this->http->options['headers']['Content-Type']);
        $this->assertSame('xml', $this->http->options['_format']);
    }

    public function test_put_xml_forces_xml_even_when_client_format_is_json(): void
    {
        $this->client()->putXml('/ISAPI/Event/notification/httpHosts/1', ['HttpHostNotification' => []]);

        $this->assertStringContainsString('format=xml', $this->http->uri);
        $this->assertSame('xml', $this->http->options['_format']);
        $this->assertSame('application/xml', $this->http->options['headers']['Content-Type']);
        $this->assertSame('application/xml', $this->http->options['headers']['Accept']);
    }

    public function test_multipart_requests_do_not_set_content_type(): void
    {
        $this->client()->postMultipart('/ISAPI/Intelligent/FDLib/FDSetUp', []);

        $this->assertArrayNotHasKey('Content-Type', $this->http->options['headers']);
        $this->assertSame('application/json', $this->http->options['headers']['Accept']);
    }

    public function test_missing_device_configuration_throws(): void
    {
        $this->expectException(HikvisionException::class);
        $this->expectExceptionMessage('Device configuration not found');

        new HikvisionClient($this->http, new DigestAuthenticator, [
            'default' => 'missing',
            'format' => 'json',
            'devices' => [],
        ]);
    }

    public function test_missing_username_throws(): void
    {
        $this->expectException(HikvisionException::class);
        $this->expectExceptionMessage('Username is required');

        new HikvisionClient($this->http, new DigestAuthenticator, $this->config(['username' => '']));
    }

    public function test_missing_password_throws(): void
    {
        $this->expectException(HikvisionException::class);
        $this->expectExceptionMessage('Password is required');

        new HikvisionClient($this->http, new DigestAuthenticator, $this->config(['password' => '']));
    }
}
