<?php

declare(strict_types=1);

namespace Shaykhnazar\HikvisionIsapi\Exceptions;

class HikvisionException extends \Exception
{
    public function __construct(
        string $message = '',
        int $code = 0,
        ?\Throwable $previous = null,
        private readonly ?int $statusCode = null,
        private readonly ?string $responseBody = null,
    ) {
        parent::__construct($message, $code, $previous);
    }

    /**
     * HTTP status returned by the device, when the failure produced a response.
     */
    public function statusCode(): ?int
    {
        return $this->statusCode;
    }

    /**
     * Raw response body from the device, when one was returned.
     *
     * Never contains biometric payloads: those are only ever sent by this client,
     * not returned by the endpoints that can fail this way.
     */
    public function responseBody(): ?string
    {
        return $this->responseBody;
    }

    /**
     * Whether retrying the same call can plausibly succeed.
     *
     * Callers that queue work against devices (sync workers, edge agents) use this
     * to decide between backing off and failing the job permanently.
     */
    public function isRetryable(): bool
    {
        return false;
    }
}
