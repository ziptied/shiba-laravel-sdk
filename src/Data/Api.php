<?php

declare(strict_types=1);

namespace Bentonow\ShibaLaravel\Data;

use Illuminate\Support\Str;
use YorCreative\LaravelArgonautDTO\ArgonautDTO;

abstract class ApiData extends ArgonautDTO
{
    public function __construct(array $attributes = [])
    {
        parent::__construct(self::camelize($attributes));
    }

    /** @return array<string, mixed> */
    public function toApiArray(): array
    {
        return self::withoutEmpty(self::snake($this->toArray()));
    }

    private static function camelize(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        if (array_is_list($value)) {
            return array_map(self::camelize(...), $value);
        }

        $result = [];

        foreach ($value as $key => $item) {
            $result[Str::camel((string) $key)] = self::camelize($item);
        }

        return $result;
    }

    private static function snake(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        if (array_is_list($value)) {
            return array_map(self::snake(...), $value);
        }

        $result = [];

        foreach ($value as $key => $item) {
            $result[Str::snake((string) $key)] = self::snake($item);
        }

        return $result;
    }

    /** @return array<string, mixed> */
    private static function withoutEmpty(array $value): array
    {
        return array_filter($value, static fn (mixed $item): bool => $item !== null && $item !== []);
    }
}

final class User extends ApiData
{
    public ?int $id = null;

    public ?string $email = null;

    public ?string $firstName = null;

    public ?string $lastName = null;

    public ?string $timeZone = null;

    public ?string $locale = null;

    public ?string $createdAt = null;

    public ?string $updatedAt = null;

    public int|string|null $teamId = null;

    public ?string $teamName = null;
}

final class SendResponse extends ApiData
{
    public bool $queued = false;

    public ?string $messageId = null;

    public ?string $from = null;

    /** @var list<string> */
    public array $to = [];

    public ?string $subject = null;

    /** @var array<string, mixed> */
    public array $uniqueArgs = [];
}

final class Tenant extends ApiData
{
    public ?int $id = null;

    public ?string $name = null;

    public ?string $slug = null;

    public bool $suspended = false;
}

final class TenantData extends ApiData
{
    public ?string $name = null;

    public ?string $slug = null;

    public function __construct(string $name, ?string $slug = null)
    {
        parent::__construct(compact('name', 'slug'));
    }
}

final class AssignedIp extends ApiData
{
    public ?string $address = null;

    public ?string $status = null;
}

final class Cluster extends ApiData
{
    protected array $casts = ['assignedIp' => AssignedIp::class];

    public ?int $id = null;

    public ?string $name = null;

    public ?string $region = null;

    public ?string $size = null;

    public ?string $plan = null;

    public ?int $emailsPerHour = null;

    public ?string $ipAssignment = null;

    public ?string $desiredStatus = null;

    public ?string $status = null;

    public ?string $smtpEndpoint = null;

    public ?string $httpEndpoint = null;

    public ?AssignedIp $assignedIp = null;

    public bool $sendingReady = false;
}

final class ClusterData extends ApiData
{
    public ?string $name = null;

    public ?string $size = null;

    public ?string $region = null;

    public ?string $plan = null;

    public function __construct(
        ?string $name = null,
        ?string $size = null,
        ?string $region = null,
        ?string $plan = null,
    ) {
        parent::__construct(compact('name', 'size', 'region', 'plan'));
    }
}

final class SendingDomain extends ApiData
{
    protected array $casts = ['tenant' => Tenant::class];

    public ?int $id = null;

    public ?string $name = null;

    public int|string|null $tenantId = null;

    public ?Tenant $tenant = null;

    public ?string $dkimStatus = null;

    public ?string $returnPathStatus = null;

    public ?string $dmarcStatus = null;

    public ?string $dkimCnameHost = null;

    public ?string $dkimCnameTarget = null;

    public ?string $dmarcHost = null;

    public ?string $dmarcRecord = null;

    public ?string $returnPathHost = null;

    public ?string $returnPathTarget = null;

    public bool $suspended = false;

    public bool $primary = false;
}

final class SendingDomainData extends ApiData
{
    public ?string $name = null;

    public int|string|null $tenantId = null;

    public function __construct(string $name, int|string|null $tenantId = null)
    {
        parent::__construct(compact('name', 'tenantId'));
    }
}

final class CanISendThisToken extends ApiData
{
    public ?string $token = null;

    public ?string $email = null;

    public ?int $pollAfterSeconds = null;

    public ?string $pollPath = null;

    public ?string $reportUrl = null;
}

final class CanISendThisScore extends ApiData
{
    public ?int $pass = null;

    public ?int $total = null;
}

final class CanISendThisCheck extends ApiData
{
    public ?string $id = null;

    public ?string $kind = null;

    public ?string $detail = null;
}

final class CanISendThisReportDetails extends ApiData
{
    public ?string $kind = null;

    public ?string $detail = null;
}

final class CanISendThisSpamAssassin extends ApiData
{
    public ?float $score = null;
}

final class CanISendThisReport extends ApiData
{
    protected array $casts = [
        'score' => CanISendThisScore::class,
        'checks' => [CanISendThisCheck::class],
        'spf' => CanISendThisReportDetails::class,
        'dkim' => CanISendThisReportDetails::class,
        'dmarc' => CanISendThisReportDetails::class,
        'spamassassin' => CanISendThisSpamAssassin::class,
    ];

    public ?string $status = null;

    public ?string $reportUrl = null;

    public ?string $subject = null;

    public ?string $from = null;

    public ?CanISendThisScore $score = null;

    /** @var list<CanISendThisCheck> */
    public array $checks = [];

    public ?CanISendThisReportDetails $spf = null;

    public ?CanISendThisReportDetails $dkim = null;

    public ?CanISendThisReportDetails $dmarc = null;

    public ?CanISendThisSpamAssassin $spamassassin = null;
}

final class Inbox extends ApiData
{
    public ?int $id = null;

    public ?string $name = null;

    public ?string $address = null;

    public ?string $localPart = null;

    public ?string $host = null;

    public int|string|null $tenantId = null;

    public ?string $webhookUrl = null;

    public ?string $webhookFormat = null;

    public ?string $webhookSecret = null;

    public ?string $mxStatus = null;

    public ?int $retentionHours = null;
}

final class InboxData extends ApiData
{
    public ?string $name = null;

    public ?string $webhookUrl = null;

    public ?string $webhookFormat = null;

    public int|string|null $tenantId = null;

    public ?int $retentionHours = null;

    public function __construct(
        string $name,
        ?string $webhookUrl = null,
        ?string $webhookFormat = null,
        int|string|null $tenantId = null,
        ?int $retentionHours = null,
    ) {
        parent::__construct(compact('name', 'webhookUrl', 'webhookFormat', 'tenantId', 'retentionHours'));
    }
}

final class InboundAttachment extends ApiData
{
    public ?string $filename = null;

    public ?string $contentType = null;

    public ?int $size = null;

    public ?string $contentBase64 = null;
}

final class InboundMessage extends ApiData
{
    protected array $casts = ['attachments' => [InboundAttachment::class]];

    public ?int $id = null;

    public ?int $inboxId = null;

    public ?string $to = null;

    public ?string $from = null;

    public ?string $subject = null;

    public ?string $text = null;

    public ?string $html = null;

    /** @var array<string, mixed> */
    public array $headers = [];

    /** @var array<string, mixed> */
    public array $envelope = [];

    /** @var list<InboundAttachment> */
    public array $attachments = [];

    public ?string $expiresAt = null;

    public ?string $drainStatus = null;

    public ?string $raw = null;

    /** @param array<string, mixed> $attributes */
    public function __construct(array $attributes = [])
    {
        $headers = $attributes['headers'] ?? [];
        $envelope = $attributes['envelope'] ?? [];

        parent::__construct($attributes);

        $this->headers = is_array($headers) ? $headers : [];
        $this->envelope = is_array($envelope) ? $envelope : [];
    }
}

final class AttachmentDownload extends ApiData
{
    public ?string $content = null;

    public ?string $filename = null;

    public ?string $contentType = null;
}

final class EventFilters extends ApiData
{
    public ?int $messageId = null;

    public ?string $recipient = null;

    public int|string|null $tenantId = null;

    public ?string $eventType = null;

    public ?string $since = null;

    public ?string $until = null;

    public function __construct(
        ?int $messageId = null,
        ?string $recipient = null,
        int|string|null $tenantId = null,
        ?string $eventType = null,
        ?string $since = null,
        ?string $until = null,
    ) {
        parent::__construct(compact('messageId', 'recipient', 'tenantId', 'eventType', 'since', 'until'));
    }
}

final class MessageEvent extends ApiData
{
    public ?int $id = null;

    public ?string $eventType = null;

    public ?string $recipient = null;

    public ?string $sender = null;

    public ?string $messageId = null;

    public ?string $occurredAt = null;

    public ?string $drainStatus = null;

    public int|string|null $tenantId = null;

    public ?int $sendingDomainId = null;

    public ?int $nodeId = null;

    /** @var array<string, mixed> */
    public array $payload = [];

    /** @param array<string, mixed> $attributes */
    public function __construct(array $attributes = [])
    {
        $payload = $attributes['payload'] ?? [];

        parent::__construct($attributes);

        $this->payload = is_array($payload) ? $payload : [];
    }
}

final class SmtpCredential extends ApiData
{
    protected array $casts = ['tenant' => Tenant::class];

    public ?int $id = null;

    public ?string $username = null;

    public ?string $password = null;

    public int|string|null $tenantId = null;

    public ?Tenant $tenant = null;

    public ?int $clusterId = null;
}

final class SmtpCredentialData extends ApiData
{
    public int|string|null $tenantId = null;

    public function __construct(int|string|null $tenantId = null)
    {
        parent::__construct(compact('tenantId'));
    }
}

final class WebhookEndpoint extends ApiData
{
    public ?int $id = null;

    public ?string $url = null;

    public bool $enabled = false;

    public ?int $clusterId = null;

    public int|string|null $tenantId = null;

    /** @var list<string> */
    public array $eventTypes = [];

    public ?string $lastSuccessAt = null;

    public ?string $lastFailureAt = null;

    public ?int $consecutiveFailures = null;

    public ?string $secret = null;
}

final class WebhookEndpointData extends ApiData
{
    public ?string $url = null;

    /** @var list<string> */
    public array $eventTypes = [];

    public ?int $clusterId = null;

    public int|string|null $tenantId = null;

    /** @param list<string> $eventTypes */
    public function __construct(
        string $url,
        array $eventTypes = [],
        ?int $clusterId = null,
        int|string|null $tenantId = null,
    ) {
        parent::__construct(compact('url', 'eventTypes', 'clusterId', 'tenantId'));
    }
}

final class Suppression extends ApiData
{
    protected array $casts = ['tenant' => Tenant::class];

    public ?int $id = null;

    public ?string $email = null;

    public ?string $reason = null;

    public int|string|null $tenantId = null;

    public ?Tenant $tenant = null;

    public ?string $createdAt = null;
}

final class SuppressionData extends ApiData
{
    public ?string $email = null;

    public int|string|null $tenantId = null;

    public function __construct(string $email, int|string|null $tenantId = null)
    {
        parent::__construct(compact('email', 'tenantId'));
    }
}

final class SuppressionFilters extends ApiData
{
    public ?string $after = null;

    public ?int $limit = null;

    public function __construct(?string $after = null, ?int $limit = null)
    {
        parent::__construct(compact('after', 'limit'));
    }
}

final class FirewallEntry extends ApiData
{
    public ?int $id = null;

    public ?string $list = null;

    public ?string $value = null;

    public ?string $createdAt = null;
}

final class Firewall extends ApiData
{
    protected array $casts = ['entries' => [FirewallEntry::class]];

    /** @var list<string> */
    public array $enabledChecks = [];

    /** @var list<string> */
    public array $availableChecks = [];

    /** @var list<FirewallEntry> */
    public array $entries = [];
}

final class FirewallData extends ApiData
{
    /** @var list<string> */
    public array $enabledChecks = [];

    /** @param list<string> $enabledChecks */
    public function __construct(array $enabledChecks)
    {
        parent::__construct(compact('enabledChecks'));
    }
}

final class FirewallEntryData extends ApiData
{
    public ?string $list = null;

    public ?string $value = null;

    public function __construct(string $list, string $value)
    {
        parent::__construct(compact('list', 'value'));
    }
}

final class HttpInjectAttachment extends ApiData
{
    public ?string $fileName = null;

    public ?string $contentType = null;

    public ?string $data = null;

    public bool $base64 = true;

    public ?string $contentId = null;

    public ?string $disposition = null;

    public function __construct(
        string $fileName,
        string $contentType,
        string $data,
        bool $base64 = true,
        ?string $contentId = null,
        ?string $disposition = null,
    ) {
        parent::__construct(compact('fileName', 'contentType', 'data', 'base64', 'contentId', 'disposition'));
    }
}

final class HttpInjectContent extends ApiData
{
    public ?string $textBody = null;

    public ?string $htmlBody = null;

    /** @var array<string, string> */
    public array $headers = [];

    /** @var list<HttpInjectAttachment|array<string, mixed>> */
    public array $attachments = [];

    /** @param array<string, string> $headers @param list<HttpInjectAttachment|array<string, mixed>> $attachments */
    public function __construct(
        ?string $textBody = null,
        ?string $htmlBody = null,
        array $headers = [],
        array $attachments = [],
    ) {
        $this->textBody = $textBody;
        $this->htmlBody = $htmlBody;
        $this->headers = $headers;
        $this->attachments = $attachments;
    }

    /** @return array<string, mixed> */
    public function toApiArray(): array
    {
        return array_filter([
            'text_body' => $this->textBody,
            'html_body' => $this->htmlBody,
            'headers' => $this->headers,
            'attachments' => array_map(
                static fn (HttpInjectAttachment|array $attachment): array => $attachment instanceof HttpInjectAttachment
                    ? $attachment->toApiArray()
                    : $attachment,
                $this->attachments,
            ),
        ], static fn (mixed $value): bool => $value !== null && $value !== []);
    }
}

final class HttpInjectPayload extends ApiData
{
    public ?string $envelopeSender = null;

    public string|array|HttpInjectContent|null $content = null;

    /** @var list<array{email: string}> */
    public array $recipients = [];

    /** @param list<string|array{email: string}> $recipients */
    public function __construct(string $envelopeSender, string|array|HttpInjectContent $content, array $recipients)
    {
        $recipients = array_map(
            static fn (string|array $recipient): array => is_string($recipient) ? ['email' => $recipient] : $recipient,
            $recipients,
        );
        parent::__construct(compact('envelopeSender', 'content', 'recipients'));
    }

    /** @return array<string, mixed> */
    public function toApiArray(): array
    {
        $content = $this->content instanceof HttpInjectContent
            ? $this->content->toApiArray()
            : $this->content;

        return array_filter([
            'envelope_sender' => $this->envelopeSender,
            'content' => $content,
            'recipients' => $this->recipients,
        ], static fn (mixed $value): bool => $value !== null && $value !== []);
    }
}

final class HttpInjectResult extends ApiData
{
    public ?int $successCount = null;

    public ?int $failCount = null;

    /** @var list<array<string, mixed>> */
    public array $failedRecipients = [];

    /** @var list<array<string, mixed>> */
    public array $errors = [];
}
