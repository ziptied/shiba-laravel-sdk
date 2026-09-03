<?php

declare(strict_types=1);

namespace Bentonow\ShibaLaravel\Http\Controllers;

use Bentonow\ShibaLaravel\Data\WebhookPayload;
use Bentonow\ShibaLaravel\Events\WebhookReceived;
use Illuminate\Http\Request;
use InvalidArgumentException;
use JsonException;
use Symfony\Component\HttpFoundation\Response;

final class WebhookController
{
    public function __invoke(Request $request): Response
    {
        try {
            $decoded = json_decode($request->getContent(), true, 512, JSON_THROW_ON_ERROR);

            if (! is_array($decoded)) {
                throw new InvalidArgumentException('A Shiba webhook payload must be a JSON array.');
            }

            $payload = WebhookPayload::fromArray($decoded);
        } catch (InvalidArgumentException|JsonException) {
            return response()->json(['message' => 'Invalid Shiba webhook payload.'], 422);
        }

        event(new WebhookReceived($payload));

        return response()->noContent();
    }
}
