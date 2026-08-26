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
    | buffer_seconds: refresh the token this many seconds before it expires
    |                 to avoid using a token right at its expiry edge.
    */
    'token' => [
        'cache_store' => env('NOTIFICATION_TOKEN_CACHE_STORE', null), // null = default store
        'cache_key'   => env('NOTIFICATION_TOKEN_CACHE_KEY', 'esanj_notification_access_token'),
        'buffer_seconds' => 60,
    ],

    /*
    |--------------------------------------------------------------------------
    | Retry Policy
    |--------------------------------------------------------------------------
    | attempts  : total number of attempts (1 = no retry)
    | sleep_ms  : milliseconds to wait between retries
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
    | HTTP Timeout
    |--------------------------------------------------------------------------
    */
    'timeout' => 30,

    /*
    |--------------------------------------------------------------------------
    | Logging
    |--------------------------------------------------------------------------
    */
    'logging' => [
        'channel' => env('NOTIFICATION_LOG_CHANNEL', null), // null = default channel
    ],
];