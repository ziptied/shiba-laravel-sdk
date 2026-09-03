<?php

declare(strict_types=1);

namespace Bentonow\ShibaLaravel\Data;

use InvalidArgumentException;

final class WebhookEvent extends ApiData
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function __construct(
        public string $event,
        public string $email,
        public array $attributes,
    ) {}

    /**
     * @param  array<string, mixed>  $attributes
     */
    public static function fromArray(array $attributes): self
    {
        $event = $attributes['event'] ?? null;
        $email = $attributes['email'] ?? null;

        if (! is_string($event) || $event === '' || ! is_string($email) || $email === '') {
            throw new InvalidArgumentException('A Shiba webhook event requires event and email strings.');
        }

        return new self($event, $email, $attributes);
    }
}
