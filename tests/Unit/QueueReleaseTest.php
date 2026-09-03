<?php

declare(strict_types=1);

use Bentonow\ShibaLaravel\Exceptions\RateLimitExceeded;
use Illuminate\Contracts\Queue\Job;
use Illuminate\Queue\Events\JobExceptionOccurred;

it('releases a throttled queue job for the configured delay', function (): void {
    $job = Mockery::mock(Job::class);
    $job->expects('isDeletedOrReleased')->andReturnFalse();
    $job->expects('release')->with(3600);

    event(new JobExceptionOccurred('sync', $job, new RateLimitExceeded(3600)));
});
