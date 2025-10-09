<?php

declare(strict_types=1);

namespace Shaykhnazar\HikvisionIsapi\Client\Contracts;

interface HttpClientInterface
{
    public function get(string $uri, array $options = []): array;

    public function post(string $uri, array $data = [], array $options = []): array;

    public function put(string $uri, array $data = [], array $options = []): array;

    public function delete(string $uri, array $options = []): array;
}
