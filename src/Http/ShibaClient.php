<?php

declare(strict_types=1);

namespace Bentonow\ShibaLaravel\Http;

use Bentonow\ShibaLaravel\Data\ApiData;
use Bentonow\ShibaLaravel\Data\AttachmentDownload;
use Bentonow\ShibaLaravel\Data\CanISendThisReport;
use Bentonow\ShibaLaravel\Data\CanISendThisToken;
use Bentonow\ShibaLaravel\Data\Cluster;
use Bentonow\ShibaLaravel\Data\ClusterData;
use Bentonow\ShibaLaravel\Data\EventFilters;
use Bentonow\ShibaLaravel\Data\Firewall;
use Bentonow\ShibaLaravel\Data\FirewallData;
use Bentonow\ShibaLaravel\Data\FirewallEntry;
use Bentonow\ShibaLaravel\Data\FirewallEntryData;
use Bentonow\ShibaLaravel\Data\HttpInjectPayload;
use Bentonow\ShibaLaravel\Data\HttpInjectResult;
use Bentonow\ShibaLaravel\Data\InboundMessage;
use Bentonow\ShibaLaravel\Data\Inbox;
use Bentonow\ShibaLaravel\Data\InboxData;
use Bentonow\ShibaLaravel\Data\MessageEvent;
use Bentonow\ShibaLaravel\Data\SendingDomain;
use Bentonow\ShibaLaravel\Data\SendingDomainData;
use Bentonow\ShibaLaravel\Data\SendPayload;
use Bentonow\ShibaLaravel\Data\SendResponse;
use Bentonow\ShibaLaravel\Data\SmtpCredential;
use Bentonow\ShibaLaravel\Data\SmtpCredentialData;
use Bentonow\ShibaLaravel\Data\Suppression;
use Bentonow\ShibaLaravel\Data\SuppressionData;
use Bentonow\ShibaLaravel\Data\SuppressionFilters;
use Bentonow\ShibaLaravel\Data\Tenant;
use Bentonow\ShibaLaravel\Data\TenantData;
use Bentonow\ShibaLaravel\Data\User;
use Bentonow\ShibaLaravel\Data\WebhookEndpoint;
use Bentonow\ShibaLaravel\Data\WebhookEndpointData;
use Bentonow\ShibaLaravel\Exceptions\PostShibaApiException;
use Bentonow\ShibaLaravel\Exceptions\RateLimitExceeded;
use Saloon\Enums\Method;
use Saloon\Exceptions\Request\FatalRequestException;
use Saloon\Http\Response;
use Symfony\Component\Mailer\Exception\TransportException;

final class ShibaClient
{
    public function whoAmI(): User
    {
        return $this->resource($this->request(Method::GET, '/api/v1/users/me'), User::class, 'user');
    }

    public function send(SendPayload $payload): SendResponse
    {
        return $this->resource(
            $this->request(
                Method::POST,
                $this->configuredSendEndpoint(),
                ['send' => $payload->toApiArray()],
                headers: $this->idempotencyHeaders($payload),
            ),
            SendResponse::class,
            'send',
        );
    }

    public function sendEmail(SendPayload $payload): SendResponse
    {
        return $this->resource(
            $this->request(
                Method::POST,
                (string) config('shiba.email_endpoint', '/api/v1/emails'),
                $payload->toApiArray(),
                headers: $this->idempotencyHeaders($payload),
            ),
            SendResponse::class,
        );
    }

    /** @return list<Cluster> */
    public function clusters(int|string $teamId): array
    {
        return $this->resources($this->request(Method::GET, $this->teamPath($teamId, 'clusters')), Cluster::class, 'clusters');
    }

    public function cluster(int|string $id): Cluster
    {
        return $this->resource($this->request(Method::GET, '/api/v1/clusters/'.$this->id($id)), Cluster::class, 'cluster');
    }

    public function createCluster(int|string $teamId, ClusterData $data): Cluster
    {
        return $this->resource($this->request(Method::POST, $this->teamPath($teamId, 'clusters'), ['cluster' => $data->toApiArray()]), Cluster::class, 'cluster');
    }

    public function updateCluster(int|string $id, ClusterData $data): Cluster
    {
        return $this->resource($this->request(Method::PATCH, '/api/v1/clusters/'.$this->id($id), ['cluster' => $data->toApiArray()]), Cluster::class, 'cluster');
    }

    public function suspendCluster(int|string $id): Cluster
    {
        return $this->resource($this->request(Method::POST, '/api/v1/clusters/'.$this->id($id).'/suspend'), Cluster::class, 'cluster');
    }

    public function resumeCluster(int|string $id): Cluster
    {
        return $this->resource($this->request(Method::POST, '/api/v1/clusters/'.$this->id($id).'/resume'), Cluster::class, 'cluster');
    }

    public function deleteCluster(int|string $id): void
    {
        $this->request(Method::DELETE, '/api/v1/clusters/'.$this->id($id));
    }

    /** @return list<SendingDomain> */
    public function sendingDomains(int|string $teamId): array
    {
        return $this->resources($this->request(Method::GET, $this->teamPath($teamId, 'sending_domains')), SendingDomain::class, 'sending_domains');
    }

    public function sendingDomain(int|string $id): SendingDomain
    {
        return $this->resource($this->request(Method::GET, '/api/v1/sending_domains/'.$this->id($id)), SendingDomain::class, 'sending_domain');
    }

    public function createSendingDomain(int|string $teamId, SendingDomainData $data): SendingDomain
    {
        return $this->resource($this->request(Method::POST, $this->teamPath($teamId, 'sending_domains'), ['sending_domain' => $data->toApiArray()]), SendingDomain::class, 'sending_domain');
    }

    public function verifySendingDomain(int|string $id): SendingDomain
    {
        return $this->domainAction($id, 'verify');
    }

    public function suspendSendingDomain(int|string $id): SendingDomain
    {
        return $this->domainAction($id, 'suspend');
    }

    public function resumeSendingDomain(int|string $id): SendingDomain
    {
        return $this->domainAction($id, 'resume');
    }

    public function makeSendingDomainPrimary(int|string $id): SendingDomain
    {
        return $this->domainAction($id, 'make_primary');
    }

    public function canISendThis(int|string $id): CanISendThisToken
    {
        return $this->resource($this->request(Method::POST, '/api/v1/sending_domains/'.$this->id($id).'/can_i_send_this'), CanISendThisToken::class);
    }

    public function canISendThisReport(int|string $id, string $token): CanISendThisReport
    {
        return $this->resource($this->request(Method::GET, '/api/v1/sending_domains/'.$this->id($id).'/can_i_send_this', query: ['token' => $token]), CanISendThisReport::class, 'report');
    }

    public function deleteSendingDomain(int|string $id): void
    {
        $this->request(Method::DELETE, '/api/v1/sending_domains/'.$this->id($id));
    }

    /** @return list<Tenant> */
    public function tenants(int|string $teamId): array
    {
        return $this->resources($this->request(Method::GET, $this->teamPath($teamId, 'tenants')), Tenant::class, 'tenants');
    }

    public function tenant(int|string $id): Tenant
    {
        return $this->resource($this->request(Method::GET, '/api/v1/tenants/'.$this->id($id)), Tenant::class, 'tenant');
    }

    public function createTenant(int|string $teamId, TenantData $data): Tenant
    {
        return $this->resource($this->request(Method::POST, $this->teamPath($teamId, 'tenants'), ['tenant' => $data->toApiArray()]), Tenant::class, 'tenant');
    }

    public function suspendTenant(int|string $id): Tenant
    {
        return $this->tenantAction($id, 'suspend');
    }

    public function resumeTenant(int|string $id): Tenant
    {
        return $this->tenantAction($id, 'resume');
    }

    public function deleteTenant(int|string $id): void
    {
        $this->request(Method::DELETE, '/api/v1/tenants/'.$this->id($id));
    }

    /** @return list<Inbox> */
    public function inboxes(int|string $teamId): array
    {
        return $this->resources($this->request(Method::GET, $this->teamPath($teamId, 'inboxes')), Inbox::class, 'inboxes');
    }

    public function inbox(int|string $id): Inbox
    {
        return $this->resource($this->request(Method::GET, '/api/v1/inboxes/'.$this->id($id)), Inbox::class, 'inbox');
    }

    public function createInbox(int|string $teamId, InboxData $data): Inbox
    {
        return $this->resource($this->request(Method::POST, $this->teamPath($teamId, 'inboxes'), ['inbox' => $data->toApiArray()]), Inbox::class, 'inbox');
    }

    public function deleteInbox(int|string $id): Inbox
    {
        return $this->resource($this->request(Method::DELETE, '/api/v1/inboxes/'.$this->id($id)), Inbox::class, 'inbox');
    }

    public function verifyInbox(int|string $id): Inbox
    {
        return $this->resource($this->request(Method::POST, '/api/v1/inboxes/'.$this->id($id).'/verify'), Inbox::class, 'inbox');
    }

    /** @return list<InboundMessage> */
    public function inboundMessages(int|string $inboxId): array
    {
        return $this->resources($this->request(Method::GET, $this->inboxMessagesPath($inboxId)), InboundMessage::class, 'inbound_messages');
    }

    public function inboundMessage(int|string $inboxId, int|string $id): InboundMessage
    {
        return $this->resource($this->request(Method::GET, $this->inboxMessagesPath($inboxId).'/'.$this->id($id)), InboundMessage::class, 'inbound_message');
    }

    public function downloadInboundAttachment(int|string $inboxId, int|string $messageId, int $index): AttachmentDownload
    {
        if ($index < 1) {
            throw new \InvalidArgumentException('Inbound attachment indexes start at 1.');
        }

        $response = $this->request(Method::GET, $this->inboxMessagesPath($inboxId).'/'.$this->id($messageId).'/attachments/'.$index);
        $psr = $response->getPsrResponse();

        return new AttachmentDownload([
            'content' => $response->body(),
            'filename' => $this->filenameFromDisposition($psr->getHeaderLine('Content-Disposition')),
            'content_type' => $psr->getHeaderLine('Content-Type') ?: null,
        ]);
    }

    /** @return list<MessageEvent> */
    public function messageEvents(int|string $teamId, int|string $clusterId, ?EventFilters $filters = null): array
    {
        return $this->resources($this->request(Method::GET, $this->teamPath($teamId, 'clusters/'.$this->id($clusterId).'/message_events'), query: $filters?->toApiArray() ?? []), MessageEvent::class, 'message_events');
    }

    public function messageEvent(int|string $id): MessageEvent
    {
        return $this->resource($this->request(Method::GET, '/api/v1/message_events/'.$this->id($id)), MessageEvent::class, 'message_event');
    }

    public function createSmtpCredential(int|string $teamId, int|string $clusterId, SmtpCredentialData $data): SmtpCredential
    {
        return $this->resource($this->request(Method::POST, $this->teamPath($teamId, 'clusters/'.$this->id($clusterId).'/smtp_credentials'), ['smtp_credential' => $data->toApiArray()]), SmtpCredential::class, 'smtp_credential');
    }

    public function deleteSmtpCredential(int|string $teamId, int|string $clusterId, int|string $id): SmtpCredential
    {
        return $this->resource($this->request(Method::DELETE, $this->teamPath($teamId, 'clusters/'.$this->id($clusterId).'/smtp_credentials/'.$this->id($id))), SmtpCredential::class, 'smtp_credential');
    }

    /** @return list<WebhookEndpoint> */
    public function webhookEndpoints(int|string $teamId): array
    {
        return $this->resources($this->request(Method::GET, $this->teamPath($teamId, 'webhook_endpoints')), WebhookEndpoint::class, 'webhook_endpoints');
    }

    public function webhookEndpoint(int|string $id): WebhookEndpoint
    {
        return $this->resource($this->request(Method::GET, '/api/v1/webhook_endpoints/'.$this->id($id)), WebhookEndpoint::class, 'webhook_endpoint');
    }

    public function createWebhookEndpoint(int|string $teamId, WebhookEndpointData $data): WebhookEndpoint
    {
        return $this->resource($this->request(Method::POST, $this->teamPath($teamId, 'webhook_endpoints'), ['webhook_endpoint' => $data->toApiArray()]), WebhookEndpoint::class, 'webhook_endpoint');
    }

    /** @return list<Suppression> */
    public function suppressions(int|string $teamId, ?SuppressionFilters $filters = null): array
    {
        return $this->resources($this->request(Method::GET, $this->teamPath($teamId, 'suppressions'), query: $filters?->toApiArray() ?? []), Suppression::class, 'suppressions');
    }

    public function createSuppression(int|string $teamId, SuppressionData $data): Suppression
    {
        return $this->resource($this->request(Method::POST, $this->teamPath($teamId, 'suppressions'), ['suppression' => $data->toApiArray()]), Suppression::class, 'suppression');
    }

    public function deleteSuppression(int|string $id): void
    {
        $this->request(Method::DELETE, '/api/v1/suppressions/'.$this->id($id));
    }

    public function firewall(int|string $teamId): Firewall
    {
        return $this->resource($this->request(Method::GET, $this->teamPath($teamId, 'firewall')), Firewall::class, 'firewall');
    }

    public function updateFirewall(int|string $teamId, FirewallData $data): Firewall
    {
        return $this->resource($this->request(Method::PATCH, $this->teamPath($teamId, 'firewall'), ['firewall' => $data->toApiArray()]), Firewall::class, 'firewall');
    }

    public function addFirewallEntry(int|string $teamId, FirewallEntryData $data): FirewallEntry
    {
        return $this->resource($this->request(Method::POST, $this->teamPath($teamId, 'firewall_entries'), ['firewall_entry' => $data->toApiArray()]), FirewallEntry::class, 'firewall_entry');
    }

    public function deleteFirewallEntry(int|string $id): void
    {
        $this->request(Method::DELETE, '/api/v1/firewall_entries/'.$this->id($id));
    }

    public function inject(HttpInjectPayload $payload, string $username, string $password): HttpInjectResult
    {
        return $this->resource($this->request(
            Method::POST,
            (string) config('shiba.inject_endpoint', '/api/inject/v1'),
            $payload->toApiArray(),
            baseUrl: (string) config('shiba.inject_base_url', 'https://inject.postshiba.com'),
            username: $username,
            password: $password,
        ), HttpInjectResult::class);
    }

    private function domainAction(int|string $id, string $action): SendingDomain
    {
        return $this->resource($this->request(Method::POST, '/api/v1/sending_domains/'.$this->id($id).'/'.$action), SendingDomain::class, 'sending_domain');
    }

    private function tenantAction(int|string $id, string $action): Tenant
    {
        return $this->resource($this->request(Method::POST, '/api/v1/tenants/'.$this->id($id).'/'.$action), Tenant::class, 'tenant');
    }

    private function configuredSendEndpoint(): string
    {
        $endpoint = (string) config('shiba.endpoint');

        foreach (['team_id' => config('shiba.team_id'), 'cluster_id' => config('shiba.cluster_id')] as $name => $value) {
            if (! str_contains($endpoint, '{'.$name.'}')) {
                continue;
            }

            if ((! is_string($value) && ! is_int($value)) || trim((string) $value) === '') {
                throw new TransportException(sprintf('The SHIBA_%s environment variable is not configured.', strtoupper($name)));
            }

            $endpoint = str_replace('{'.$name.'}', rawurlencode((string) $value), $endpoint);
        }

        return $endpoint;
    }

    /** @return array<string, string> */
    private function idempotencyHeaders(SendPayload $payload): array
    {
        return $payload->idempotencyKey === null ? [] : ['Idempotency-Key' => $payload->idempotencyKey];
    }

    private function teamPath(int|string $teamId, string $suffix): string
    {
        return '/api/v1/teams/'.$this->id($teamId).'/'.$suffix;
    }

    private function inboxMessagesPath(int|string $inboxId): string
    {
        return '/api/v1/inboxes/'.$this->id($inboxId).'/inbound_messages';
    }

    private function id(int|string $id): string
    {
        return rawurlencode((string) $id);
    }

    private function filenameFromDisposition(string $disposition): ?string
    {
        if ($disposition === '') {
            return null;
        }

        if (preg_match("/filename\\*=[^']*''([^;]+)/i", $disposition, $matches) === 1) {
            return rawurldecode(trim($matches[1], " \t\""));
        }

        if (preg_match('/filename="((?:\\\\.|[^"\\\\])*)"/i', $disposition, $matches) === 1) {
            return stripcslashes($matches[1]);
        }

        if (preg_match('/filename=([^;\s]+)/i', $disposition, $matches) === 1) {
            return trim($matches[1], " \t'\"");
        }

        return null;
    }

    /** @param array<string, mixed> $body @param array<string, mixed> $query @param array<string, string> $headers */
    private function request(
        Method $method,
        string $endpoint,
        array $body = [],
        array $query = [],
        ?string $baseUrl = null,
        ?string $username = null,
        ?string $password = null,
        array $headers = [],
    ): Response {
        $apiKey = config('shiba.api_key');

        if ($username === null && (! is_string($apiKey) || trim($apiKey) === '')) {
            throw new TransportException('The SHIBA_API_KEY environment variable is not configured.');
        }

        $connector = $username === null
            ? ShibaConnector::token((string) ($baseUrl ?? config('shiba.base_url')), (string) $apiKey)
            : ShibaConnector::basic((string) ($baseUrl ?? config('shiba.inject_base_url')), $username, (string) $password);

        try {
            $request = new ShibaRequest($method, $endpoint, $body, $query);

            foreach ($headers as $name => $value) {
                $request->headers()->add($name, $value);
            }

            $response = $connector->send($request);
        } catch (FatalRequestException $exception) {
            throw new TransportException('PostShiba could not be reached: '.$exception->getMessage(), 0, $exception);
        }

        $data = $this->responseData($response);
        $error = is_string($data['error'] ?? null) ? $data['error'] : null;

        if ($response->status() === 429 && $error === 'throttled') {
            throw new RateLimitExceeded(max(1, (int) config('shiba.retry_delay', 3600)));
        }

        if ($response->successful()) {
            return $response;
        }

        $message = is_string($data['message'] ?? null)
            ? $data['message']
            : ($error ?? $response->getPsrResponse()->getReasonPhrase());

        throw new PostShibaApiException(
            $response->status(),
            $error,
            sprintf('PostShiba rejected the request with HTTP %d: %s', $response->status(), $message),
        );
    }

    /** @return array<array-key, mixed> */
    private function responseData(Response $response): array
    {
        try {
            $data = $response->json();
        } catch (\Throwable) {
            return [];
        }

        return $data;
    }

    /**
     * @template T of ApiData
     *
     * @param  class-string<T>  $class
     * @return T
     */
    private function resource(Response $response, string $class, ?string $key = null): ApiData
    {
        $data = $this->responseData($response);

        if ($key !== null && is_array($data[$key] ?? null)) {
            $data = $data[$key];
        }

        return new $class($data);
    }

    /**
     * @template T of ApiData
     *
     * @param  class-string<T>  $class
     * @return list<T>
     */
    private function resources(Response $response, string $class, string $key): array
    {
        $data = $this->responseData($response);
        $items = array_is_list($data) ? $data : ($data[$key] ?? []);

        return array_map(fn (mixed $item) => new $class(is_array($item) ? $item : []), $items);
    }
}
