<?php

declare(strict_types=1);

use Bentonow\ShibaLaravel\Events\WebhookReceived;
use Bentonow\ShibaLaravel\Http\InboundWebhook;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;

it('accepts a valid signed webhook and dispatches a typed event', function (): void {
    config([
        'shiba.webhook.secret' => 'webhook-secret',
        'shiba.webhook.timestamp_tolerance' => 300,
    ]);
    $timestamp = (string) time();
    $body = json_encode([[
        'event' => 'delivered',
        'email' => 'recipient@example.com',
    ]], JSON_THROW_ON_ERROR);
    Event::fake([WebhookReceived::class]);

    $response = $this->call('POST', '/shiba/webhooks', [], [], [], [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_X_CAPSULE_TIMESTAMP' => $timestamp,
        'HTTP_X_CAPSULE_SIGNATURE' => 'sha256='.hash_hmac('sha256', $timestamp.'.'.$body, 'webhook-secret'),
    ], $body);

    $response->assertNoContent();
    Event::assertDispatched(WebhookReceived::class, fn (WebhookReceived $event): bool => $event->payload->events[0]->event === 'delivered'
        && $event->payload->events[0]->email === 'recipient@example.com'
    );
});

it('rejects an invalid signed webhook', function (): void {
    config([
        'shiba.webhook.secret' => 'webhook-secret',
        'shiba.webhook.timestamp_tolerance' => 300,
    ]);

    $response = $this->post('/shiba/webhooks', [], [
        'X-Capsule-Timestamp' => (string) time(),
        'X-Capsule-Signature' => 'sha256=invalid',
    ]);

    $response->assertForbidden();
});

it('parses a complete inbound message with a caller-provided inbox secret', function (): void {
    $timestamp = (string) time();
    $body = json_encode([
        'id' => 'message-123',
        'inbox_id' => 'inbox-123',
        'tenant_id' => 'tenant-123',
        'from' => 'customer@example.com',
        'to' => ['support@example.com'],
        'subject' => 'Question',
        'text' => 'Hello',
        'thread_id' => 'thread-123',
        'message_id' => '<message-123@example.com>',
        'in_reply_to' => '<parent@example.com>',
        'references' => ['<root@example.com>', '<parent@example.com>'],
    ], JSON_THROW_ON_ERROR);
    $request = Request::create('/inbound', 'POST', server: [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_X_CAPSULE_TIMESTAMP' => $timestamp,
        'HTTP_X_CAPSULE_SIGNATURE' => 'sha256='.hash_hmac('sha256', $timestamp.'.'.$body, 'inbox-secret'),
    ], content: $body);

    $message = app(InboundWebhook::class)->parse($request, 'inbox-secret');

    expect($message->inboxId)->toBe('inbox-123')
        ->and($message->tenantId)->toBe('tenant-123')
        ->and($message->threadId)->toBe('thread-123')
        ->and($message->references)->toBe(['<root@example.com>', '<parent@example.com>'])
        ->and($message->rawPayload['message_id'])->toBe('<message-123@example.com>');
});

it('rejects an inbound payload without its routing identifiers', function (): void {
    $timestamp = (string) time();
    $body = json_encode(['inbox_id' => 'inbox-123'], JSON_THROW_ON_ERROR);
    $request = Request::create('/inbound', 'POST', server: [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_X_CAPSULE_TIMESTAMP' => $timestamp,
        'HTTP_X_CAPSULE_SIGNATURE' => 'sha256='.hash_hmac('sha256', $timestamp.'.'.$body, 'inbox-secret'),
    ], content: $body);

    expect(fn (): mixed => app(InboundWebhook::class)->parse($request, 'inbox-secret'))
        ->toThrow(InvalidArgumentException::class);
});
