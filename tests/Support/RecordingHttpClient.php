<?php

declare(strict_types=1);

namespace Shaykhnazar\HikvisionIsapi\Tests\Support;

use Shaykhnazar\HikvisionIsapi\Client\Contracts\HttpClientInterface;

/**
 * Captures the last call made by HikvisionClient so URI and option building can be asserted.
 */
class RecordingHttpClient implements HttpClientInterface
{
    public string $uri = '';

    /** @var array<string, mixed> */
    public array $options = [];

    /** @var array<string, mixed> */
    public array $data = [];

    public function get(string $uri, array $options = []): array
    {
        return $this->record($uri, [], $options);
    }

    public function post(string $uri, array $data = [], array $options = []): array
    {
        return $this->record($uri, $data, $options);
    }

    public function put(string $uri, array $data = [], array $options = []): array
    {
        return $this->record($uri, $data, $options);
    }

    public function delete(string $uri, array $options = []): array
    {
        return $this->record($uri, [], $options);
    }

    public function postMultipart(string $uri, array $multipart = [], array $options = []): array
    {
        return $this->record($uri, $multipart, $options);
    }

    public function putMultipart(string $uri, array $multipart = [], array $options = []): array
    {
        return $this->record($uri, $multipart, $options);
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>
     */
    private function record(string $uri, array $data, array $options): array
    {
        $this->uri = $uri;
        $this->data = $data;
        $this->options = $options;

        return [];
    }
}
