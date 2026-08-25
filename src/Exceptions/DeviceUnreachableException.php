<?php

declare(strict_types=1);

namespace Shaykhnazar\HikvisionIsapi\Exceptions;

/**
 * The device could not be contacted at all: DNS failure, refused connection,
 * or a connect/read timeout. No HTTP response was received.
 */
class DeviceUnreachableException extends HikvisionException
{
    public function isRetryable(): bool
    {
        return true;
    }
}
