<?php

declare(strict_types=1);

namespace Shaykhnazar\HikvisionIsapi\Client;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Shaykhnazar\HikvisionIsapi\Client\Contracts\HttpClientInterface;
use Shaykhnazar\HikvisionIsapi\Exceptions\HikvisionException;

class HttpClient implements HttpClientInterface
{
    private Client $client;

    public function __construct()
    {
        $this->client = new Client();
    }

    public function get(string $uri, array $options = []): array
    {
        return $this->request('GET', $uri, $options);
    }

    public function post(string $uri, array $data = [], array $options = []): array
    {
        $options['json'] = $data;
        return $this->request('POST', $uri, $options);
    }

    public function put(string $uri, array $data = [], array $options = []): array
    {
        $options['json'] = $data;
        return $this->request('PUT', $uri, $options);
    }

    public function delete(string $uri, array $options = []): array
    {
        return $this->request('DELETE', $uri, $options);
    }

    public function postMultipart(string $uri, array $multipart = [], array $options = []): array
    {
        $options['multipart'] = $multipart;
        return $this->request('POST', $uri, $options);
    }

    private function request(string $method, string $uri, array $options): array
    {
        try {
            $response = $this->client->request($method, $uri, $options);

            $body = $response->getBody()->getContents();
            $contentType = $response->getHeader('Content-Type')[0] ?? '';

            if (str_contains($contentType, 'application/json')) {
                return json_decode($body, true) ?? [];
            }

            return ['raw' => $body];
        } catch (GuzzleException $e) {
            throw new HikvisionException(
                "HTTP request failed: {$e->getMessage()}",
                $e->getCode(),
                $e
            );
        }
    }
}
