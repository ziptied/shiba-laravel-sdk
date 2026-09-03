<?php

declare(strict_types=1);

namespace Bentonow\ShibaLaravel\Http;

use Saloon\Contracts\Authenticator;
use Saloon\Http\Auth\BasicAuthenticator;
use Saloon\Http\Auth\TokenAuthenticator;
use Saloon\Http\Connector;

final class ShibaConnector extends Connector
{
    public function __construct(
        private readonly string $baseUrl,
        private readonly Authenticator $shibaAuthenticator,
    ) {}

    public function resolveBaseUrl(): string
    {
        return rtrim($this->baseUrl, '/');
    }

    protected function defaultHeaders(): array
    {
        return ['Accept' => 'application/json'];
    }

    protected function defaultAuth(): Authenticator
    {
        return $this->shibaAuthenticator;
    }

    public static function token(string $baseUrl, string $apiKey): self
    {
        return new self($baseUrl, new TokenAuthenticator($apiKey));
    }

    public static function basic(string $baseUrl, string $username, string $password): self
    {
        return new self($baseUrl, new BasicAuthenticator($username, $password));
    }
}
