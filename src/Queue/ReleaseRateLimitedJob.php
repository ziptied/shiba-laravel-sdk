<?php

declare(strict_types=1);

namespace Bentonow\ShibaLaravel\Queue;

use Bentonow\ShibaLaravel\Exceptions\RateLimitExceeded;
use Illuminate\Queue\Events\JobExceptionOccurred;

final class ReleaseRateLimitedJob
{
    public function handle(JobExceptionOccurred $event): void
    {
        if (! $event->exception instanceof RateLimitExceeded || $event->job->isDeletedOrReleased()) {
            return;
        }

        $event->job->release($event->exception->retryDelay());
    }
}
