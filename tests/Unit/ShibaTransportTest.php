<?php

declare(strict_types=1);

use Bentonow\ShibaLaravel\Exceptions\PostShibaApiException;
use Bentonow\ShibaLaravel\Exceptions\RateLimitExceeded;
use Bentonow\ShibaLaravel\Http\ShibaClient;
use Bentonow\ShibaLaravel\Mail\ShibaMessagePayload;
use Bentonow\ShibaLaravel\Mail\ShibaTransport;
use Illuminate\Support\Facades\Mail;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;

beforeEach(function (): void {
    MockClient::destroyGlobal();
});

afterEach(function (): void {
    MockClient::destroyGlobal();
});

function shibaEmail(): Email
{
    $email = (new Email)
        ->from(new Address('sender@example.com', 'Sender'))
        ->to(new Address('recipient@example.com'))
        ->subject('Subject')
        ->text('Plain text')
        ->html('<p>HTML</p>')
        ->attach('hello', 'note.txt', 'text/plain');

    $email->getHeaders()->addTextHeader('X-Request-ID', 'request-123');
    $email->getHeaders()->addTextHeader('X-Capsule-Unique-Args', '{"campaign_id":"cmp_123"}');

    return $email;
}

function shibaTransport(): ShibaTransport
{
    return new ShibaTransport(new ShibaClient, new ShibaMessagePayload);
}

it('posts a Laravel mail message to PostShiba', function (): void {
    config([
        'shiba.api_key' => 'shiba-key',
        'shiba.base_url' => 'https://app.postshiba.com',
    ]);
    $mock = MockClient::global([MockResponse::make(['queued' => true], 201)]);

    shibaTransport()->send(
        shibaEmail(),
        new Envelope(new Address('sender@example.com'), [new Address('recipient@example.com')]),
    );

    $request = $mock->getLastResponse()->getPsrRequest();
    $body = json_decode((string) $request->getBody(), true, 512, JSON_THROW_ON_ERROR);

    expect((string) $request->getUri())->toBe('https://app.postshiba.com/api/v1/teams/3/clusters/3/sends')
        ->and($request->getHeaderLine('Authorization'))->toBe('Bearer shiba-key')
        ->and($body['send']['from'])->toBe('sender@example.com')
        ->and($body['send']['to'])->toBe(['recipient@example.com'])
        ->and($body['send']['text'])->toBe('Plain text')
        ->and($body['send']['html'])->toBe('<p>HTML</p>')
        ->and($body['send']['headers'])->toBe(['X-Request-ID' => 'request-123'])
        ->and($body['send']['attachments'])->toBe([[
            'filename' => 'note.txt',
            'content_type' => 'text/plain',
            'content' => base64_encode('hello'),
        ]])
        ->and($body['send']['unique_args'])->toBe(['campaign_id' => 'cmp_123']);
});

it('registers shiba as a Laravel mailer', function (): void {
    config(['shiba.api_key' => 'shiba-key', 'mail.default' => 'shiba']);
    $mock = MockClient::global([MockResponse::make(['queued' => true], 201)]);

    Mail::raw('Plain text', function ($message): void {
        $message->from('sender@example.com')
            ->to('recipient@example.com')
            ->subject('Subject');
    });

    expect($mock->getLastResponse()?->json('queued'))->toBeTrue();
});

it('turns PostShiba throttling into a delayed queue release signal', function (): void {
    config([
        'shiba.api_key' => 'shiba-key',
        'shiba.retry_delay' => 3600,
    ]);
    MockClient::global([MockResponse::make(['error' => 'throttled'], 429)]);

    expect(fn (): mixed => shibaTransport()->send(
        shibaEmail(),
        new Envelope(new Address('sender@example.com'), [new Address('recipient@example.com')]),
    ))
        ->toThrow(RateLimitExceeded::class);
});

it('preserves the documented REST error code and status', function (): void {
    config(['shiba.api_key' => 'shiba-key']);
    MockClient::global([MockResponse::make(['error' => 'domain_unverified'], 403)]);

    try {
        shibaTransport()->send(
            shibaEmail(),
            new Envelope(new Address('sender@example.com'), [new Address('recipient@example.com')]),
        );
    } catch (PostShibaApiException $exception) {
        expect($exception->status)->toBe(403)
            ->and($exception->error)->toBe('domain_unverified')
            ->and($exception->getCode())->toBe(403);

        return;
    }

    throw new RuntimeException('Expected PostShibaApiException was not thrown.');
});
