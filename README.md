# Shiba Laravel SDK

Laravel 13 mail transport, typed PostShiba API client, and signed webhook receiver.

## Requirements

- PHP 8.4+
- Laravel 13+

## Install

```bash
composer require bentonow/shiba-laravel-sdk
```

Set the REST credentials and mail cluster in `.env`:

```dotenv
SHIBA_API_KEY=your-api-key
SHIBA_TEAM_ID=your-team-id
SHIBA_CLUSTER_ID=your-cluster-id
SHIBA_WEBHOOK_SECRET=your-webhook-secret
```

The package uses Saloon v4 for HTTP requests and
[Laravel Argonaut DTO](https://github.com/ziptied/Laravel-Argonaut-DTO) for request and response DTOs. The API defaults to
`https://app.postshiba.com` and the documented team/cluster send endpoint.
`SHIBA_BASE_URL`, `SHIBA_ENDPOINT`, `SHIBA_RETRY_DELAY`,
`SHIBA_INJECT_BASE_URL`, and `SHIBA_INJECT_ENDPOINT` are configurable.

Set Laravel's mailer:

```dotenv
MAIL_MAILER=shiba
```

The `shiba` mailer serializes text, HTML, headers, unique arguments, and
attachments into the REST `send.attachments` array. A documented `429
throttled` response raises `RateLimitExceeded`; queued mail is released after
`SHIBA_RETRY_DELAY` seconds (3600 by default).

## API client

Resolve `Bentonow\ShibaLaravel\Http\ShibaClient` from the container. It covers
authentication, sending, clusters, sending domains, tenants, inboxes, inbound
messages and attachment downloads, message events, SMTP credentials, webhook
endpoints, suppressions, firewall settings/entries, and HTTP inject.

Request DTOs are in `Bentonow\ShibaLaravel\Data`, for example:

```php
use Bentonow\ShibaLaravel\Data\TenantData;
use Bentonow\ShibaLaravel\Http\ShibaClient;

$tenant = app(ShibaClient::class)->createTenant(
    config('shiba.team_id'),
    new TenantData('Transactional'),
);
```

HTTP inject uses the SMTP credential returned by PostShiba:

```php
$result = app(ShibaClient::class)->inject($payload, $username, $password);
```

## Webhooks

The signed endpoint `POST /shiba/webhooks` is registered automatically. Set
`SHIBA_WEBHOOK_SECRET` and optionally `SHIBA_WEBHOOK_PATH` or
`SHIBA_WEBHOOK_TIMESTAMP_TOLERANCE`. The controller validates the signed JSON
array and dispatches `Bentonow\ShibaLaravel\Events\WebhookReceived`; listen
for that event to store or process delivery events.

See the [PostShiba documentation](https://app.postshiba.com/docs) for API,
HTTP inject, attachment, and webhook details.
