<?php

declare(strict_types=1);

namespace Bentonow\ShibaLaravel\Mail;

use Bentonow\ShibaLaravel\Http\ShibaClient;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractTransport;

final class ShibaTransport extends AbstractTransport
{
    public function __construct(
        private readonly ShibaClient $client,
        private readonly ShibaMessagePayload $payload,
    ) {
        parent::__construct();
    }

    protected function doSend(SentMessage $message): void
    {
        $this->client->send($this->payload->fromSentMessage($message));
    }

    public function __toString(): string
    {
        return 'shiba';
    }
}
