<?php

declare(strict_types=1);

namespace Shaykhnazar\HikvisionIsapi\Facades;

use Illuminate\Support\Facades\Facade;
use Shaykhnazar\HikvisionIsapi\Client\HikvisionClient;

/**
 * @method static array get(string $endpoint, array $queryParams = [])
 * @method static array post(string $endpoint, array $data = [], array $queryParams = [])
 * @method static array put(string $endpoint, array $data = [], array $queryParams = [])
 * @method static array delete(string $endpoint, array $queryParams = [])
 */
class HikvisionIsapi extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return HikvisionClient::class;
    }
}
