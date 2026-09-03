<?php

declare(strict_types=1);

namespace Bentonow\ShibaLaravel\Data;

final class SendPayload extends ApiData
{
    /**
     * @param  list<string>  $to
     * @param  list<string>  $cc
     * @param  list<string>  $bcc
     * @param  array<string, string>  $headers
     * @param  list<array{filename: string, content_type: string, content: string}>  $attachments
     * @param  array<string, mixed>  $uniqueArgs
     */
    public function __construct(
        public ?string $from,
        public array $to,
        public array $cc = [],
        public array $bcc = [],
        public ?string $replyTo = null,
        public ?string $subject = null,
        public ?string $text = null,
        public ?string $html = null,
        public array $headers = [],
        public array $attachments = [],
        public array $uniqueArgs = [],
        public int|string|null $tenant = null,
        public bool|string|int|null $sandbox = null,
        public ?string $idempotencyKey = null,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(int $depth = 3): array
    {
        return array_filter([
            'from' => $this->from,
            'to' => $this->to,
            'cc' => $this->cc,
            'bcc' => $this->bcc,
            'reply_to' => $this->replyTo,
            'subject' => $this->subject,
            'text' => $this->text,
            'html' => $this->html,
            'headers' => $this->headers,
            'attachments' => $this->attachments,
            'unique_args' => $this->uniqueArgs,
            'tenant' => $this->tenant,
            'sandbox' => $this->sandbox,
        ], static fn (mixed $value): bool => $value !== null && $value !== []);
    }

    /** @return array<string, mixed> */
    public function toApiArray(): array
    {
        return array_filter([
            'from' => $this->from,
            'to' => $this->to,
            'cc' => $this->cc,
            'bcc' => $this->bcc,
            'reply_to' => $this->replyTo,
            'subject' => $this->subject,
            'text' => $this->text,
            'html' => $this->html,
            'headers' => $this->headers,
            'attachments' => $this->attachments,
            'unique_args' => $this->uniqueArgs,
            'tenant' => $this->tenant,
            'sandbox' => $this->sandbox,
        ], static fn (mixed $value): bool => $value !== null && $value !== []);
    }
}
