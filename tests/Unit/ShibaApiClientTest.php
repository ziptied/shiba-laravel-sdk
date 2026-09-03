<?php

declare(strict_types=1);

use Bentonow\ShibaLaravel\Data\Cluster;
use Bentonow\ShibaLaravel\Data\ClusterData;
use Bentonow\ShibaLaravel\Data\EventFilters;
use Bentonow\ShibaLaravel\Data\FirewallData;
use Bentonow\ShibaLaravel\Data\FirewallEntryData;
use Bentonow\ShibaLaravel\Data\HttpInjectAttachment;
use Bentonow\ShibaLaravel\Data\HttpInjectContent;
use Bentonow\ShibaLaravel\Data\HttpInjectPayload;
use Bentonow\ShibaLaravel\Data\InboxData;
use Bentonow\ShibaLaravel\Data\SendingDomainData;
use Bentonow\ShibaLaravel\Data\SendPayload;
use Bentonow\ShibaLaravel\Data\SmtpCredentialData;
use Bentonow\ShibaLaravel\Data\SuppressionData;
use Bentonow\ShibaLaravel\Data\SuppressionFilters;
use Bentonow\ShibaLaravel\Data\TenantData;
use Bentonow\ShibaLaravel\Data\WebhookEndpointData;
use Bentonow\ShibaLaravel\Exceptions\PostShibaApiException;
use Bentonow\ShibaLaravel\Http\ShibaClient;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;

beforeEach(function (): void {
    config(['shiba.api_key' => 'shiba-key']);
    MockClient::destroyGlobal();
});

afterEach(function (): void {
    MockClient::destroyGlobal();
});

it('maps the documented PostShiba API to typed DTOs and requests', function (): void {
    $responses = array_fill(0, 48, MockResponse::make([], 200));
    $responses[32] = MockResponse::make('attachment', 200, [
        'Content-Type' => 'text/plain',
        'Content-Disposition' => 'attachment; filename="note.txt"',
    ]);
    $mock = MockClient::global($responses);
    $client = new ShibaClient;

    $client->whoAmI();
    $client->send(new SendPayload('from@example.com', ['to@example.com'], attachments: [[
        'filename' => 'note.txt',
        'content_type' => 'text/plain',
        'content' => base64_encode('hello'),
    ]], sandbox: true, idempotencyKey: 'send-123'));
    $client->clusters(3);
    $client->cluster(4);
    $client->createCluster(3, new ClusterData('cluster', plan: 'small'));
    $client->updateCluster(4, new ClusterData(plan: 'large'));
    $client->suspendCluster(4);
    $client->resumeCluster(4);
    $client->deleteCluster(4);
    $client->sendingDomains(3);
    $client->sendingDomain(5);
    $client->createSendingDomain(3, new SendingDomainData('example.com'));
    $client->verifySendingDomain(5);
    $client->suspendSendingDomain(5);
    $client->resumeSendingDomain(5);
    $client->makeSendingDomainPrimary(5);
    $client->canISendThis(5);
    $client->canISendThisReport(5, 'report-token');
    $client->deleteSendingDomain(5);
    $client->tenants(3);
    $client->tenant(7);
    $client->createTenant(3, new TenantData('Tenant', 'tenant'));
    $client->suspendTenant(7);
    $client->resumeTenant(7);
    $client->deleteTenant(7);
    $client->inboxes(3);
    $client->inbox(10);
    $client->createInbox(3, new InboxData('agent'));
    $client->deleteInbox(10);
    $client->verifyInbox(10);
    $client->inboundMessages(10);
    $client->inboundMessage(10, 20);
    $attachment = $client->downloadInboundAttachment(10, 20, 1);
    $client->messageEvents(3, 4, new EventFilters(recipient: 'to@example.com', since: '2026-01-01'));
    $client->messageEvent(20);
    $client->createSmtpCredential(3, 4, new SmtpCredentialData(7));
    $client->deleteSmtpCredential(3, 4, 8);
    $client->webhookEndpoints(3);
    $client->webhookEndpoint(9);
    $client->createWebhookEndpoint(3, new WebhookEndpointData('https://example.com/hook', ['delivered']));
    $client->suppressions(3, new SuppressionFilters(limit: 25));
    $client->createSuppression(3, new SuppressionData('blocked@example.com'));
    $client->deleteSuppression(11);
    $client->firewall(3);
    $client->updateFirewall(3, new FirewallData(['spf', 'dkim']));
    $client->addFirewallEntry(3, new FirewallEntryData('deny', '203.0.113.4'));
    $client->deleteFirewallEntry(12);
    $client->inject(new HttpInjectPayload(
        'from@example.com',
        new HttpInjectContent(
            textBody: 'body',
            headers: ['X-Capsule-Unique-Args' => '{"campaign_id":"cmp_123"}'],
            attachments: [new HttpInjectAttachment('note.txt', 'text/plain', base64_encode('hello'))],
        ),
        ['to@example.com'],
    ), 'smtp-user', 'smtp-pass');

    $requests = array_map(
        static fn ($response) => $response->getPsrRequest(),
        $mock->getRecordedResponses(),
    );
    $tenantBody = json_decode((string) $requests[21]->getBody(), true, 512, JSON_THROW_ON_ERROR);
    $sendBody = json_decode((string) $requests[1]->getBody(), true, 512, JSON_THROW_ON_ERROR);
    $injectBody = json_decode((string) $requests[47]->getBody(), true, 512, JSON_THROW_ON_ERROR);

    expect(count($requests))->toBe(48)
        ->and(array_map(static fn ($request): string => $request->getMethod().' '.$request->getUri(), $requests))->toBe([
            'GET https://app.postshiba.com/api/v1/users/me',
            'POST https://app.postshiba.com/api/v1/teams/3/clusters/3/sends',
            'GET https://app.postshiba.com/api/v1/teams/3/clusters',
            'GET https://app.postshiba.com/api/v1/clusters/4',
            'POST https://app.postshiba.com/api/v1/teams/3/clusters',
            'PATCH https://app.postshiba.com/api/v1/clusters/4',
            'POST https://app.postshiba.com/api/v1/clusters/4/suspend',
            'POST https://app.postshiba.com/api/v1/clusters/4/resume',
            'DELETE https://app.postshiba.com/api/v1/clusters/4',
            'GET https://app.postshiba.com/api/v1/teams/3/sending_domains',
            'GET https://app.postshiba.com/api/v1/sending_domains/5',
            'POST https://app.postshiba.com/api/v1/teams/3/sending_domains',
            'POST https://app.postshiba.com/api/v1/sending_domains/5/verify',
            'POST https://app.postshiba.com/api/v1/sending_domains/5/suspend',
            'POST https://app.postshiba.com/api/v1/sending_domains/5/resume',
            'POST https://app.postshiba.com/api/v1/sending_domains/5/make_primary',
            'POST https://app.postshiba.com/api/v1/sending_domains/5/can_i_send_this',
            'GET https://app.postshiba.com/api/v1/sending_domains/5/can_i_send_this?token=report-token',
            'DELETE https://app.postshiba.com/api/v1/sending_domains/5',
            'GET https://app.postshiba.com/api/v1/teams/3/tenants',
            'GET https://app.postshiba.com/api/v1/tenants/7',
            'POST https://app.postshiba.com/api/v1/teams/3/tenants',
            'POST https://app.postshiba.com/api/v1/tenants/7/suspend',
            'POST https://app.postshiba.com/api/v1/tenants/7/resume',
            'DELETE https://app.postshiba.com/api/v1/tenants/7',
            'GET https://app.postshiba.com/api/v1/teams/3/inboxes',
            'GET https://app.postshiba.com/api/v1/inboxes/10',
            'POST https://app.postshiba.com/api/v1/teams/3/inboxes',
            'DELETE https://app.postshiba.com/api/v1/inboxes/10',
            'POST https://app.postshiba.com/api/v1/inboxes/10/verify',
            'GET https://app.postshiba.com/api/v1/inboxes/10/inbound_messages',
            'GET https://app.postshiba.com/api/v1/inboxes/10/inbound_messages/20',
            'GET https://app.postshiba.com/api/v1/inboxes/10/inbound_messages/20/attachments/1',
            'GET https://app.postshiba.com/api/v1/teams/3/clusters/4/message_events?recipient=to%40example.com&since=2026-01-01',
            'GET https://app.postshiba.com/api/v1/message_events/20',
            'POST https://app.postshiba.com/api/v1/teams/3/clusters/4/smtp_credentials',
            'DELETE https://app.postshiba.com/api/v1/teams/3/clusters/4/smtp_credentials/8',
            'GET https://app.postshiba.com/api/v1/teams/3/webhook_endpoints',
            'GET https://app.postshiba.com/api/v1/webhook_endpoints/9',
            'POST https://app.postshiba.com/api/v1/teams/3/webhook_endpoints',
            'GET https://app.postshiba.com/api/v1/teams/3/suppressions?limit=25',
            'POST https://app.postshiba.com/api/v1/teams/3/suppressions',
            'DELETE https://app.postshiba.com/api/v1/suppressions/11',
            'GET https://app.postshiba.com/api/v1/teams/3/firewall',
            'PATCH https://app.postshiba.com/api/v1/teams/3/firewall',
            'POST https://app.postshiba.com/api/v1/teams/3/firewall_entries',
            'DELETE https://app.postshiba.com/api/v1/firewall_entries/12',
            'POST https://inject.postshiba.com/api/inject/v1',
        ])
        ->and($tenantBody['tenant'])->toBe(['name' => 'Tenant', 'slug' => 'tenant'])
        ->and($injectBody['recipients'])->toBe([['email' => 'to@example.com']])
        ->and($injectBody['content']['attachments'][0]['file_name'])->toBe('note.txt')
        ->and($injectBody['content']['headers']['X-Capsule-Unique-Args'])->toBe('{"campaign_id":"cmp_123"}')
        ->and($attachment->content)->toBe('attachment')
        ->and($attachment->filename)->toBe('note.txt')
        ->and($attachment->contentType)->toBe('text/plain')
        ->and($sendBody['send']['attachments'])->not->toBeEmpty()
        ->and($requests[1]->getHeaderLine('Idempotency-Key'))->toBe('send-123')
        ->and($sendBody['send']['sandbox'])->toBeTrue()
        ->and($requests[47]->getHeaderLine('Authorization'))->toBe('Basic '.base64_encode('smtp-user:smtp-pass'))
        ->and($injectBody['content']['attachments'][0]['file_name'])->toBe('note.txt')
        ->and($injectBody['content']['headers']['X-Capsule-Unique-Args'])->toBe('{"campaign_id":"cmp_123"}');
});

it('serializes DTOs without dropping zero or false values', function (): void {
    $cluster = new Cluster([
        'id' => 4,
        'sending_ready' => false,
        'assigned_ip' => ['address' => '203.0.113.2', 'status' => 'assigned'],
    ]);

    expect($cluster->id)->toBe(4)
        ->and($cluster->sendingReady)->toBeFalse()
        ->and($cluster->assignedIp?->address)->toBe('203.0.113.2')
        ->and($cluster->toApiArray()['sending_ready'])->toBeFalse();
});

it('sends an application email payload without the generic send envelope', function (): void {
    config(['shiba.api_key' => 'shiba-key']);
    $mock = MockClient::global([MockResponse::make([
        'queued' => true,
        'message_id' => 'message-123',
    ], 202)]);

    $response = (new ShibaClient)->sendEmail(new SendPayload(
        'from@example.com',
        ['to@example.com'],
        tenant: 'tenant-123',
        idempotencyKey: 'reply-123',
    ));

    $request = $mock->getLastResponse()->getPsrRequest();
    $body = json_decode((string) $request->getBody(), true, 512, JSON_THROW_ON_ERROR);

    expect((string) $request->getUri())->toBe('https://app.postshiba.com/api/v1/emails')
        ->and($request->getHeaderLine('Idempotency-Key'))->toBe('reply-123')
        ->and($body)->toBe([
            'from' => 'from@example.com',
            'to' => ['to@example.com'],
            'tenant' => 'tenant-123',
        ])
        ->and($body)->not->toHaveKey('send')
        ->and($response->messageId)->toBe('message-123');
});

it('maps threaded inbound messages without losing the provider payload', function (): void {
    config(['shiba.api_key' => 'shiba-key']);
    MockClient::global([MockResponse::make([
        'inbound_message' => [
            'id' => 'message-123',
            'inbox_id' => 'inbox-123',
            'tenant_id' => 'tenant-123',
            'to' => ['support@example.com'],
            'from' => 'customer@example.com',
            'subject' => 'Question',
            'text' => 'Hello',
            'thread_id' => 'thread-123',
            'message_id' => '<message-123@example.com>',
            'in_reply_to' => '<parent@example.com>',
            'references' => ['<root@example.com>', '<parent@example.com>'],
            'headers' => ['Message-ID' => '<message-123@example.com>'],
        ],
    ])]);

    $message = (new ShibaClient)->inboundMessage('inbox-123', 'message-123');

    expect($message->id)->toBe('message-123')
        ->and($message->inboxId)->toBe('inbox-123')
        ->and($message->tenantId)->toBe('tenant-123')
        ->and($message->to)->toBe(['support@example.com'])
        ->and($message->threadId)->toBe('thread-123')
        ->and($message->messageId)->toBe('<message-123@example.com>')
        ->and($message->inReplyTo)->toBe('<parent@example.com>')
        ->and($message->references)->toBe(['<root@example.com>', '<parent@example.com>'])
        ->and($message->rawPayload['thread_id'])->toBe('thread-123');
});

it('preserves every documented REST error code', function (?string $error, int $status): void {
    MockClient::global([MockResponse::make($error === null ? [] : ['error' => $error], $status)]);

    try {
        (new ShibaClient)->whoAmI();
    } catch (PostShibaApiException $exception) {
        expect($exception->status)->toBe($status)
            ->and($exception->error)->toBe($error);

        return;
    }

    throw new RuntimeException('Expected PostShibaApiException was not thrown.');
})->with([
    [null, 401],
    ['kyc_required', 403],
    ['cluster_not_ready', 403],
    ['domain_unverified', 403],
    ['credential_missing', 403],
    ['suppressed', 403],
    ['firewall', 403],
    ['invalid', 422],
    ['throttled', 429],
    ['inject_failed', 502],
]);
