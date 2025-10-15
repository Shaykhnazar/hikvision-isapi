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

    public function delete(string $endpoint, array $queryParams = []): array
    {
        $uri = $this->buildUri($endpoint, $queryParams);
        return $this->httpClient->delete($uri, $this->buildOptions());
    }

    public function postMultipart(string $endpoint, array $multipart = [], array $queryParams = []): array
    {
        $uri = $this->buildUri($endpoint, $queryParams);
        return $this->httpClient->postMultipart($uri, $multipart, $this->buildOptions());
    }

    private function buildUri(string $endpoint, array $queryParams = []): string
    {
        $queryParams['format'] = $this->format;
        $query = http_build_query($queryParams);

        return $this->baseUrl . $endpoint . ($query ? '?' . $query : '');
    }

    private function buildOptions(): array
    {
        $device = $this->config['devices'][$this->config['default']];

        $contentType = $this->format === 'xml' ? 'application/xml' : 'application/json';
        $accept = $this->format === 'xml' ? 'application/xml' : 'application/json';

        return array_merge($this->authOptions, [
            'timeout' => $device['timeout'],
            'verify' => $device['verify_ssl'],
            'headers' => [
                'Accept' => $accept,
                'Content-Type' => $contentType,
            ],
        ]);
    }
}
