<?php

declare(strict_types=1);

namespace Shaykhnazar\HikvisionIsapi\Exceptions;

/**
 * The device answered, but could not serve the request right now:
 * HTTP 408, 429, or any 5xx. Terminals return these under concurrent load,
 * during firmware housekeeping, and while a large batch is being applied.
 */
class DeviceBusyException extends HikvisionException
{
    public function isRetryable(): bool
    {
        return true;
    }
}
