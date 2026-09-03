<?php

declare(strict_types=1);

namespace Bentonow\ShibaLaravel\Http;

use Bentonow\ShibaLaravel\Data\InboundMessage;
use Illuminate\Http\Request;
use InvalidArgumentException;
use JsonException;

final readonly class InboundWebhook
{
    public function __construct(private WebhookSignature $signature) {}

    public function parse(Request $request, string $secret, ?int $tolerance = null): InboundMessage
    {
        if (! $this->signature->verify($request, $secret, $tolerance)) {
            throw new InvalidArgumentException('Invalid Shiba webhook signature.');
        }

        try {
            $payload = json_decode($request->getContent(), true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new InvalidArgumentException('Invalid Shiba inbound webhook payload.', 0, $exception);
        }

        if (! is_array($payload) || array_is_list($payload)) {
            throw new InvalidArgumentException('A Shiba inbound webhook payload must be a JSON object.');
        }

        foreach (['id', 'inbox_id'] as $field) {
            $value = $payload[$field] ?? null;

            if ((! is_int($value) && ! is_string($value)) || (is_string($value) && trim($value) === '')) {
                throw new InvalidArgumentException('A Shiba inbound webhook payload requires a '.$field.' value.');
            }
        }

        return InboundMessage::fromArray($payload);
    }
}
