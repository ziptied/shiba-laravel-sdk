<?php

declare(strict_types=1);

namespace Bentonow\ShibaLaravel\Events;

use Bentonow\ShibaLaravel\Data\WebhookPayload;

final readonly class WebhookReceived
{
    public function __construct(public WebhookPayload $payload) {}
}
