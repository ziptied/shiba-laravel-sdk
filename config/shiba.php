<?php

declare(strict_types=1);

return [
    'api_key' => env('SHIBA_API_KEY'),
    'base_url' => env('SHIBA_BASE_URL', 'https://app.postshiba.com'),
    'inject_base_url' => env('SHIBA_INJECT_BASE_URL', 'https://inject.postshiba.com'),
    'inject_endpoint' => env('SHIBA_INJECT_ENDPOINT', '/api/inject/v1'),
    'team_id' => env('SHIBA_TEAM_ID'),
    'cluster_id' => env('SHIBA_CLUSTER_ID'),
    'endpoint' => env('SHIBA_ENDPOINT', '/api/v1/teams/{team_id}/clusters/{cluster_id}/sends'),
    'retry_delay' => (int) env('SHIBA_RETRY_DELAY', 3600),

    'webhook' => [
        'path' => env('SHIBA_WEBHOOK_PATH', '/shiba/webhooks'),
        'secret' => env('SHIBA_WEBHOOK_SECRET'),
        'timestamp_tolerance' => (int) env('SHIBA_WEBHOOK_TIMESTAMP_TOLERANCE', 300),
    ],
];
