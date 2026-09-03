# Shiba Laravel SDK

Laravel 13 integration for PostShiba:

- a Laravel mail transport backed by the PostShiba REST API;
- a typed REST client for PostShiba management and sending APIs;
- HTTP inject support with SMTP credentials; and
- signed webhook verification, inbound webhook parsing, and typed events.

## Requirements

- PHP 8.4+
- Laravel 13+

## Installation

```bash
composer require bentonow/shiba-laravel-sdk
```

The service provider is discovered automatically. Publish the package config
only if you need to customize it:

```bash
php artisan vendor:publish --tag=shiba-config
```

## Configuration

Add the API key and the team/cluster used by the default mail send endpoint to
`.env`:

```dotenv
SHIBA_API_KEY=your-api-key
SHIBA_TEAM_ID=your-team-id
SHIBA_CLUSTER_ID=your-cluster-id
```

Available environment variables:

| Variable | Default | Purpose |
| --- | --- | --- |
| `SHIBA_API_KEY` | `POSTSHIBA_API_KEY` | PostShiba bearer token for the REST API and mail transport |
| `SHIBA_BASE_URL` | `POSTSHIBA_BASE_URL`, then `https://app.postshiba.com` | REST API base URL |
| `SHIBA_ENDPOINT` | `/api/v1/teams/{team_id}/clusters/{cluster_id}/sends` | Mail and `send()` endpoint |
| `SHIBA_EMAIL_ENDPOINT` | `/api/v1/emails` | Application email endpoint used by `sendEmail()` |
| `SHIBA_TEAM_ID` | — | Team ID substituted into the default send endpoint |
| `SHIBA_CLUSTER_ID` | — | Cluster ID substituted into the default send endpoint |
| `SHIBA_RETRY_DELAY` | `3600` | Queue release delay after a throttled send, in seconds |
| `SHIBA_INJECT_BASE_URL` | `https://inject.postshiba.com` | HTTP inject base URL |
| `SHIBA_INJECT_ENDPOINT` | `/api/inject/v1` | HTTP inject endpoint |
| `SHIBA_WEBHOOK_ENABLED` | `true` | Register the package's webhook route |
| `SHIBA_WEBHOOK_PATH` | `/shiba/webhooks` | Registered webhook route |
| `SHIBA_WEBHOOK_SECRET` | — | Secret used to verify webhook signatures |
| `SHIBA_WEBHOOK_TIMESTAMP_TOLERANCE` | `300` | Accepted webhook timestamp age, in seconds |

## Laravel mail transport

Set the default Laravel mailer to `shiba`:

```dotenv
MAIL_MAILER=shiba
```

The transport converts Symfony mail messages into PostShiba `send` payloads,
including:

- from, to, cc, bcc, reply-to, subject, text, and HTML;
- custom headers, excluding MIME and address headers;
- `X-Capsule-Unique-Args` as the `unique_args` object; and
- attachments as base64-encoded `send.attachments` entries; and
- inline attachments, including `cid:` replacement in HTML plus `content_id`
  and `disposition` metadata.

Laravel mail can then be used normally:

```php
use Illuminate\Support\Facades\Mail;

Mail::raw('Plain text', function ($message): void {
    $message->from('sender@example.com')
        ->to('recipient@example.com')
        ->subject('Subject');
});
```

## Typed REST client

Resolve `Bentonow\ShibaLaravel\Http\ShibaClient` from Laravel's container.
REST responses are returned as typed DTOs from
`Bentonow\ShibaLaravel\Data`; request DTOs serialize their camelCase PHP
properties to the API's snake_case fields.

### Sending

```php
use Bentonow\ShibaLaravel\Data\SendPayload;
use Bentonow\ShibaLaravel\Http\ShibaClient;

$response = app(ShibaClient::class)->send(new SendPayload(
    from: 'sender@example.com',
    to: ['recipient@example.com'],
    subject: 'Subject',
    text: 'Plain text',
    html: '<p>HTML</p>',
    uniqueArgs: ['campaign_id' => 'cmp_123'],
    idempotencyKey: 'send-123',
));

$response->queued;
$response->messageId;
```

`send()` uses the configured team/cluster endpoint and sends an
`Idempotency-Key` header when `idempotencyKey` is provided.

For the application email endpoint, use `sendEmail()`. It sends the payload at
the JSON root instead of wrapping it in `send`:

```php
use Bentonow\ShibaLaravel\Data\SendPayload;
use Bentonow\ShibaLaravel\Http\ShibaClient;

$response = app(ShibaClient::class)->sendEmail(new SendPayload(
    from: 'sender@example.com',
    to: ['recipient@example.com'],
    tenant: 'tenant-123',
));
```

### Supported client operations

`ShibaClient` currently provides:

| Area | Operations |
| --- | --- |
| Identity | `whoAmI()` |
| Sending | `send()` and `sendEmail()` |
| Clusters | list, get, create, update, suspend, resume, delete |
| Sending domains | list, get, create, verify, suspend, resume, make primary, can-send check/report, delete |
| Tenants | list, get, create, suspend, resume, delete |
| Inboxes | list, get, create, verify, delete |
| Inbound mail | list/get messages, threaded fields, raw provider payloads, and attachment downloads |
| Message events | list with filters and get by ID |
| SMTP credentials | create and delete |
| Webhook endpoints | list, get, and create |
| Suppressions | list with filters, create, and delete |
| Firewall | get/update settings, add entries, and delete entries |
| HTTP inject | `inject()` |

Use the matching `*Data` DTO for create/update operations, such as
`ClusterData`, `SendingDomainData`, `TenantData`, `InboxData`,
`SmtpCredentialData`, `WebhookEndpointData`, `SuppressionData`,
`FirewallData`, and `FirewallEntryData`.

```php
use Bentonow\ShibaLaravel\Data\TenantData;
use Bentonow\ShibaLaravel\Http\ShibaClient;

$tenant = app(ShibaClient::class)->createTenant(
    (int) config('shiba.team_id'),
    new TenantData('Transactional', 'transactional'),
);
```

### HTTP inject

HTTP inject uses the PostShiba SMTP username and password with Basic Auth:

```php
use Bentonow\ShibaLaravel\Data\HttpInjectAttachment;
use Bentonow\ShibaLaravel\Data\HttpInjectContent;
use Bentonow\ShibaLaravel\Data\HttpInjectPayload;
use Bentonow\ShibaLaravel\Http\ShibaClient;

$payload = new HttpInjectPayload(
    envelopeSender: 'sender@example.com',
    content: new HttpInjectContent(
        textBody: 'Plain text',
        htmlBody: '<p>HTML</p>',
        attachments: [
            new HttpInjectAttachment(
                'note.txt',
                'text/plain',
                base64_encode('hello'),
            ),
        ],
    ),
    recipients: ['recipient@example.com'],
);

$result = app(ShibaClient::class)->inject($payload, $username, $password);
```

`HttpInjectPayload` also accepts an already-shaped content array and recipient
arrays such as `['email' => 'recipient@example.com']`.

## Webhooks

When `SHIBA_WEBHOOK_ENABLED` is enabled, the package registers
`POST /shiba/webhooks` automatically, or the path set by `SHIBA_WEBHOOK_PATH`.
Configure the signing secret:

```dotenv
SHIBA_WEBHOOK_SECRET=your-webhook-secret
```

The middleware verifies `X-Capsule-Timestamp` and
`X-Capsule-Signature` using HMAC-SHA256 over `timestamp.raw_request_body` and
accepts signatures with or without the `sha256=` prefix. The controller then
validates the JSON array and dispatches
`Bentonow\ShibaLaravel\Events\WebhookReceived` with a typed
`WebhookPayload` containing `WebhookEvent` objects.

Listen for the event in the normal Laravel way:

```php
use Bentonow\ShibaLaravel\Events\WebhookReceived;
use Illuminate\Support\Facades\Event;

Event::listen(WebhookReceived::class, function (WebhookReceived $event): void {
    foreach ($event->payload->events as $webhookEvent) {
        // $webhookEvent->event, $webhookEvent->email, $webhookEvent->attributes
    }
});
```

Invalid signatures return `403`; invalid webhook JSON or events return `422`.

### Inbound webhooks

Use the container-resolvable `InboundWebhook` parser for PostShiba inbound
message webhooks. It verifies the same signature headers, requires `id` and
`inbox_id`, and returns an `InboundMessage` without dispatching an event:

```php
use Bentonow\ShibaLaravel\Http\InboundWebhook;

$message = app(InboundWebhook::class)->parse(request(), $inboxSecret);

$message->threadId;
$message->references;
$message->rawPayload;
```

Inbound messages support provider string or integer IDs, multiple recipients
and references, threading fields, and the original payload in `rawPayload`.
`WebhookSignature` is also container-resolvable for custom webhook controllers.

## Errors and throttling

Failed PostShiba responses throw `PostShibaApiException` with the HTTP
`status` and API `error` code. Network failures throw Symfony's
`TransportException`.

A `429` response with the `throttled` error throws `RateLimitExceeded`. When a
queued Laravel mail job encounters it, the package automatically releases the
job after `SHIBA_RETRY_DELAY` seconds.

## Development

```bash
composer test
composer lint
composer analyse
```

See the [PostShiba documentation](https://app.postshiba.com/docs) for API,
HTTP inject, attachment, and webhook details.
