<?php

declare(strict_types=1);

namespace Bentonow\ShibaLaravel\Exceptions;

final class RateLimitExceeded extends PostShibaApiException
{
    public function __construct(private readonly int $retryDelay)
    {
        parent::__construct(
            429,
            'throttled',
            sprintf('PostShiba hourly send limit reached; retrying in %d seconds.', $retryDelay),
        );
    }

    public function retryDelay(): int
    {
        return $this->retryDelay;
    }
}
