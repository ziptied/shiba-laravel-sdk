<?php

declare(strict_types=1);

namespace Bentonow\ShibaLaravel\Http\Middleware;

use Bentonow\ShibaLaravel\Http\WebhookSignature;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class VerifyWebhookSignature
{
    public function __construct(private readonly WebhookSignature $signature) {}

    public function handle(Request $request, Closure $next): Response
    {
        $secret = config('shiba.webhook.secret');

        if (! is_string($secret) || ! $this->signature->verify($request, $secret)) {
            return response()->json(['message' => 'Invalid Shiba webhook signature.'], 403);
        }

        return $next($request);
    }
}
