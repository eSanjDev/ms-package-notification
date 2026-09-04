<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Notification Service Base URL
    |--------------------------------------------------------------------------
    | The base URL of the Esanj Notification microservice.
    */
    'base_url' => env('NOTIFICATION_SERVICE_URL', 'http://localhost'),

    /*
    |--------------------------------------------------------------------------
    | OAuth Client Credentials
    |--------------------------------------------------------------------------
    */
    'client_id' => env('NOTIFICATION_CLIENT_ID'),
    'client_secret' => env('NOTIFICATION_CLIENT_SECRET'),

    /*
    |--------------------------------------------------------------------------
    | Token Cache Settings
    |--------------------------------------------------------------------------
    | buffer_seconds: refresh the token this many seconds before it expires, so it is never used
    |                 right at its expiry edge. The client reads `expires_at` when the service sends
    |                 it and falls back to `expires_in`; a token with less life left than the buffer
    |                 is still used for one call rather than rejected.
    |
    | cache_store MUST be a store every web process and queue worker can see — redis or memcached.
    | The token endpoint allows 10 requests per minute per IP; one shared token turns that into about
    | one request per hour. With a per-container store (file, array) each container fetches its own
    | token, and enough containers starting at once will exhaust that limit.
    */
    'token' => [
        'cache_store' => env('NOTIFICATION_TOKEN_CACHE_STORE', null), // null = default store
        'cache_key'   => env('NOTIFICATION_TOKEN_CACHE_KEY', 'esanj_notification_access_token'),
        'buffer_seconds' => 60,
        'encrypt' => env('NOTIFICATION_TOKEN_ENCRYPT', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | Retry Policy
    |--------------------------------------------------------------------------
    | attempts  : total number of attempts (1 = no retry)
    | sleep_ms  : base delay between retries. It doubles per attempt (1s, 2s, 4s, capped at 10s) and
    |             half of each delay is randomised, so clients that failed together do not all retry
    |             on the same tick and hammer a recovering service. 0 disables waiting entirely.
    |
    | Only GET/HEAD/OPTIONS are retried on server or connection errors. A send is retried solely when
    | idempotency is enabled below, because a lost response does not mean the service ignored the call.
    */
    'retry' => [
        'attempts' => 3,
        'sleep_ms' => 1000,
    ],

    /*
    |--------------------------------------------------------------------------
    | Idempotency
    |--------------------------------------------------------------------------
    | Enable only when the notification service honours the `Idempotency-Key` header and returns the
    | original response for a repeated key. Turning this on without server support makes retries send
    | duplicate messages.
    */
    'idempotency' => [
        'enabled' => env('NOTIFICATION_IDEMPOTENCY', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | HTTP Timeouts
    |--------------------------------------------------------------------------
    | timeout         : how long to wait for a complete response.
    | connect_timeout : how long to wait for the connection itself to open.
    */
    'timeout' => env('NOTIFICATION_TIMEOUT', 30),
    'connect_timeout' => env('NOTIFICATION_CONNECT_TIMEOUT', 10),

    /*
    |--------------------------------------------------------------------------
    | Logging
    |--------------------------------------------------------------------------
    */
    'logging' => [
        'channel' => env('NOTIFICATION_LOG_CHANNEL', null), // null = default channel
    ],
];
