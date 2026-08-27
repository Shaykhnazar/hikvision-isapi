<?php

declare(strict_types=1);

namespace Shaykhnazar\HikvisionIsapi\Client;

use Shaykhnazar\HikvisionIsapi\Authentication\Contracts\AuthenticatorInterface;
use Shaykhnazar\HikvisionIsapi\Client\Contracts\HttpClientInterface;
use Shaykhnazar\HikvisionIsapi\Exceptions\HikvisionException;

class HikvisionClient
{
    private string $baseUrl;

    private array $authOptions;

    private string $format;

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly AuthenticatorInterface $authenticator,
        private readonly array $config
    ) {
        $this->initialize();
    }

    private function initialize(): void
    {
        $device = $this->config['devices'][$this->config['default']] ?? null;

        if (!$device) {
            throw new HikvisionException('Device configuration not found');
        }

        // Validate required configuration
        if (empty($device['username'])) {
            throw new HikvisionException('Username is required in device configuration');
        }

        if (empty($device['password'])) {
            throw new HikvisionException('Password is required in device configuration. Please set HIKVISION_PASSWORD in your .env file');
        }

        $this->baseUrl = sprintf(
            '%s://%s:%s',
            $device['protocol'],
            $device['ip'],
            $device['port']
        );

        $this->authOptions = $this->authenticator->buildAuthOptions(
            $device['username'],
            $device['password']
        );

        $this->format = $this->config['format'];
    }

    public function get(string $endpoint, array $queryParams = []): array
    {
        $uri = $this->buildUri($endpoint, $queryParams);

        return $this->httpClient->get($uri, $this->buildOptions());
    }

    /*
     * Request bodies are marked sensitive so PHP redacts them from stack traces.
     *
     * Every write to a terminal carries personal data — a name, a card number, a
     * base64 face — and PHP records call arguments in a trace unless told not
     * to. The setting that tells it not to has a compiled default of 0 and is
     * not set by the official `php:*-alpine` images this ships on, so the
     * default deployment is the leaking one.
     *
     * Marking the entry points was not enough on its own: the assembled payload
     * is passed onward as an ordinary argument, so the frame for *this* call
     * recorded the face even when the caller's own parameter was redacted. This
     * is where the body actually stops.
     */
    public function post(string $endpoint, #[\SensitiveParameter] array $data = [], array $queryParams = []): array
    {
        $uri = $this->buildUri($endpoint, $queryParams);
        $options = $this->buildOptions();
        $options['_format'] = $this->format; // Pass format to HttpClient

        return $this->httpClient->post($uri, $data, $options);
    }

    public function put(string $endpoint, #[\SensitiveParameter] array $data = [], array $queryParams = []): array
    {
        $uri = $this->buildUri($endpoint, $queryParams);
        $options = $this->buildOptions();
        $options['_format'] = $this->format; // Pass format to HttpClient

        return $this->httpClient->put($uri, $data, $options);
    }

    /**
     * PUT request with forced XML format
     * Used for endpoints that require XML regardless of global format setting
     */
    public function putXml(string $endpoint, #[\SensitiveParameter] array $data = [], array $queryParams = []): array
    {
        // Force XML format in query params
        $queryParams['format'] = 'xml';
        $uri = $this->buildUri($endpoint, $queryParams);

        $options = $this->buildOptions();
        $options['_format'] = 'xml'; // Force XML format
        $options['headers']['Content-Type'] = 'application/xml';
        $options['headers']['Accept'] = 'application/xml';

        return $this->httpClient->put($uri, $data, $options);
    }

    public function delete(string $endpoint, array $queryParams = []): array
    {
        $uri = $this->buildUri($endpoint, $queryParams);

        return $this->httpClient->delete($uri, $this->buildOptions());
    }

    public function postMultipart(string $endpoint, #[\SensitiveParameter] array $multipart = [], array $queryParams = []): array
    {
        $uri = $this->buildUri($endpoint, $queryParams);

        return $this->httpClient->postMultipart($uri, $multipart, $this->buildOptions(excludeContentType: true));
    }

    public function putMultipart(string $endpoint, #[\SensitiveParameter] array $multipart = [], array $queryParams = []): array
    {
        $uri = $this->buildUri($endpoint, $queryParams);

        return $this->httpClient->putMultipart($uri, $multipart, $this->buildOptions(excludeContentType: true));
    }

    private function buildUri(string $endpoint, array $queryParams = []): string
    {
        // Only set global format if not already specified in query params
        if (!isset($queryParams['format'])) {
            $queryParams['format'] = $this->format;
        }
        $query = http_build_query($queryParams);

        return $this->baseUrl.$endpoint.($query ? '?'.$query : '');
    }

    private function buildOptions(bool $excludeContentType = false): array
    {
        $device = $this->config['devices'][$this->config['default']];

        $contentType = $this->format === 'xml' ? 'application/xml' : 'application/json';
        $accept = $this->format === 'xml' ? 'application/xml' : 'application/json';

        $headers = ['Accept' => $accept];

        // Don't set Content-Type for multipart requests (Guzzle sets it automatically)
        if (!$excludeContentType) {
            $headers['Content-Type'] = $contentType;
        }

        return array_merge($this->authOptions, [
            // Defaulted rather than assumed present. A device entry without
            // these is otherwise perfectly valid — the agent supplies them, a
            // hand-written config often will not — and reading them blind turns
            // that into a PHP warning on every single request.
            'timeout' => $device['timeout'] ?? 30,
            'verify' => $device['verify_ssl'] ?? false,
            'headers' => $headers,
        ]);
    }
}
