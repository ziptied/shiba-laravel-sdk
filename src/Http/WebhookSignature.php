<?php

declare(strict_types=1);

namespace Bentonow\ShibaLaravel\Http;

use Illuminate\Http\Request;

final class WebhookSignature
{
    public function verify(Request $request, string $secret, ?int $tolerance = null): bool
    {
        $timestamp = $request->header('X-Capsule-Timestamp');
        $signature = $request->header('X-Capsule-Signature');
        $tolerance ??= (int) config('shiba.webhook.timestamp_tolerance', 300);
        $tolerance = max(0, $tolerance);

        if ($secret === '' || ! is_string($timestamp) || ! ctype_digit($timestamp) ||
            ! is_string($signature) || abs(time() - (int) $timestamp) > $tolerance) {
            return false;
        }

        $signature = str_starts_with($signature, 'sha256=')
            ? substr($signature, 7)
            : $signature;
        $expected = hash_hmac('sha256', $timestamp.'.'.$request->getContent(), $secret);

        return hash_equals($expected, $signature);
    }
}
