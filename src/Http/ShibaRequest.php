<?php

declare(strict_types=1);

namespace Bentonow\ShibaLaravel\Http;

use Saloon\Contracts\Body\HasBody;
use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Traits\Body\HasJsonBody;

final class ShibaRequest extends Request implements HasBody
{
    use HasJsonBody;

    /** @param array<string, mixed> $requestBody @param array<string, mixed> $requestQuery */
    public function __construct(
        Method $method,
        private readonly string $endpoint,
        private readonly array $requestBody = [],
        private readonly array $requestQuery = [],
    ) {
        $this->method = $method;
    }

    public function resolveEndpoint(): string
    {
        return $this->endpoint;
    }

    protected function defaultBody(): array
    {
        return $this->requestBody;
    }

    protected function defaultQuery(): array
    {
        return $this->requestQuery;
    }
}
