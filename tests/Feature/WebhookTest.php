<?php

declare(strict_types=1);

use Bentonow\ShibaLaravel\Events\WebhookReceived;
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
