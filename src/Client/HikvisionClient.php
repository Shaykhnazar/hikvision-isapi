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

    public function post(string $endpoint, array $data = [], array $queryParams = []): array
    {
        $uri = $this->buildUri($endpoint, $queryParams);
        $options = $this->buildOptions();
        $options['_format'] = $this->format; // Pass format to HttpClient

        return $this->httpClient->post($uri, $data, $options);
    }

    public function put(string $endpoint, array $data = [], array $queryParams = []): array
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
    public function putXml(string $endpoint, array $data = [], array $queryParams = []): array
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

    public function postMultipart(string $endpoint, array $multipart = [], array $queryParams = []): array
    {
        $uri = $this->buildUri($endpoint, $queryParams);

        return $this->httpClient->postMultipart($uri, $multipart, $this->buildOptions(excludeContentType: true));
    }

    public function putMultipart(string $endpoint, array $multipart = [], array $queryParams = []): array
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
            'timeout' => $device['timeout'],
            'verify' => $device['verify_ssl'],
            'headers' => $headers,
        ]);
    }
}
