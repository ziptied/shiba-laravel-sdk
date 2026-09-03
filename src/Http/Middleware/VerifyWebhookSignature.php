<?php

declare(strict_types=1);

namespace Bentonow\ShibaLaravel\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class VerifyWebhookSignature
{
    public function handle(Request $request, Closure $next): Response
    {
        $timestamp = $request->header('X-Capsule-Timestamp');
        $signature = $request->header('X-Capsule-Signature');
        $secret = config('shiba.webhook.secret');
        $tolerance = max(0, (int) config('shiba.webhook.timestamp_tolerance', 300));

        if (! is_string($timestamp) || ! ctype_digit($timestamp) || ! is_string($signature) ||
            ! is_string($secret) || $secret === '' || abs(time() - (int) $timestamp) > $tolerance) {
            return response()->json(['message' => 'Invalid Shiba webhook signature.'], 403);
        }

        $signature = str_starts_with($signature, 'sha256=')
            ? substr($signature, 7)
            : $signature;
        $expected = hash_hmac('sha256', $timestamp.'.'.$request->getContent(), $secret);

        if (! hash_equals($expected, $signature)) {
            return response()->json(['message' => 'Invalid Shiba webhook signature.'], 403);
        }

        return $next($request);
    }
}
