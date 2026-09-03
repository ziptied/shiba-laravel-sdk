<?php

declare(strict_types=1);

namespace Bentonow\ShibaLaravel\Data;

use InvalidArgumentException;

final class WebhookPayload extends ApiData
{
    /**
     * @param  list<WebhookEvent>  $events
     */
    protected array $casts = ['events' => [WebhookEvent::class]];

    /** @param list<WebhookEvent> $events */
    public function __construct(public array $events) {}

    /**
     * @param  array<int, mixed>  $payload
     */
    public static function fromArray(array $payload): self
    {
        if (! array_is_list($payload)) {
            throw new InvalidArgumentException('A Shiba webhook payload must be a JSON array.');
        }

        return new self(array_map(static function (mixed $event): WebhookEvent {
            if (! is_array($event)) {
                throw new InvalidArgumentException('A Shiba webhook event must be a JSON object.');
            }

            return WebhookEvent::fromArray($event);
        }, $payload));
    }
}
