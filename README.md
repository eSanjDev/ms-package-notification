# Esanj Notification Client

Laravel client package for the **Esanj Notification Microservice**. Handles OAuth 2.0 token acquisition, automatic caching, token refresh, and retry logic out of the box.

**Supports:** Laravel 12 & 13 · PHP 8.2+

---

## Installation

```bash
composer require esanj/notification-client
```

Publish the config file:

```bash
php artisan vendor:publish --tag=notification-config
```

---

## Configuration

Add the following variables to your `.env` file:

```env
NOTIFICATION_SERVICE_URL=https://notification.your-domain.com
NOTIFICATION_CLIENT_ID=your-client-id
NOTIFICATION_CLIENT_SECRET=your-client-secret

# Optional
NOTIFICATION_TOKEN_CACHE_STORE=redis        # default: your app's default cache store
NOTIFICATION_TOKEN_CACHE_KEY=notif_token    # default: esanj_notification_access_token
NOTIFICATION_LOG_CHANNEL=stack              # default: your app's default log channel
```

Full config reference (`config/esanj/notification.php`):

```php
return [
    'base_url'      => env('NOTIFICATION_SERVICE_URL', 'http://localhost'),
    'client_id'     => env('NOTIFICATION_CLIENT_ID'),
    'client_secret' => env('NOTIFICATION_CLIENT_SECRET'),

    'token' => [
        'cache_store'    => env('NOTIFICATION_TOKEN_CACHE_STORE', null),
        'cache_key'      => env('NOTIFICATION_TOKEN_CACHE_KEY', 'esanj_notification_access_token'),
        'buffer_seconds' => 60,   // refresh token 60 seconds before actual expiry
    ],

    'retry' => [
        'attempts' => 3,      // total attempts including the first
        'sleep_ms' => 1000,   // milliseconds between retries
    ],

    'timeout' => 30,

    'logging' => [
        'channel' => env('NOTIFICATION_LOG_CHANNEL', null),
    ],
];
```

---

## Token Management

Token handling is **fully automatic**:

1. On the first request the package fetches a token via the OAuth 2.0 client-credentials flow (`POST /api/v1/oauth/token`).
2. The token is stored in your configured cache store with a TTL equal to `expires_in - buffer_seconds`.
3. A fast in-memory copy avoids cache I/O on subsequent calls within the same process.
4. If a request receives an `HTTP 401` or `403`, the package invalidates the cached token, fetches a fresh one, and retries — up to `retry.attempts` times.
5. If all retries fail, an `ApiException` (or `AuthenticationException`) is thrown and the error is logged.

---

## Usage

### Dependency Injection (recommended)

```php
use Esanj\NotificationClient\Contracts\NotificationClientInterface;

class OrderService
{
    public function __construct(
        private readonly NotificationClientInterface $notifier
    ) {}
}
```

### Facade

```php
use Esanj\NotificationClient\Facades\Notifier;

Notifier::send($data);
```

---

## Sending Notifications

### SMS — plain message

```php
use Esanj\NotificationClient\DTOs\SendNotificationData;
use Esanj\NotificationClient\DTOs\Payloads\SmsPayload;

$notification = $notifier->send(new SendNotificationData(
    recipient: '+989123456789',
    payload:   SmsPayload::fromMessage('Your OTP is 1234'),
    channel:   'sms',
    priority:  'high',
));

echo $notification->uuid;   // "550e8400-e29b-..."
echo $notification->status; // "pending"
```

### SMS — pattern (template code)

```php
use Esanj\NotificationClient\DTOs\Payloads\SmsPatternPayload;

$notification = $notifier->send(new SendNotificationData(
    recipient: '+989123456789',
    payload:   SmsPatternPayload::make('otp_pattern', ['code' => '1234', 'name' => 'John']),
    channel:   'sms',
));
```

### Email

```php
use Esanj\NotificationClient\DTOs\Payloads\EmailPayload;

$notification = $notifier->send(new SendNotificationData(
    recipient: 'user@example.com',
    payload:   EmailPayload::make()
                   ->subject('Welcome to our platform')
                   ->html('<h1>Hello, John!</h1><p>Your account is ready.</p>')
                   ->text('Hello, John! Your account is ready.')
                   ->from('no-reply@example.com', 'Example')
                   ->replyTo('support@example.com')
                   ->cc(['manager@example.com'])
                   ->bcc(['archive@example.com']),
    channel:   'email',
));
```

### Push Notification

```php
use Esanj\NotificationClient\DTOs\Payloads\PushPayload;

$notification = $notifier->send(new SendNotificationData(
    recipient: 'device-fcm-token',
    payload:   PushPayload::make()
                   ->title('New Order')
                   ->body('Your order #1234 has been confirmed.')
                   ->url('https://app.example.com/orders/1234')
                   ->data(['order_id' => 1234]),
    channel:   'push',
));
```

### Using a Template (any channel)

```php
use Esanj\NotificationClient\DTOs\Payloads\TemplatePayload;

$notification = $notifier->send(new SendNotificationData(
    recipient: 'user@example.com',
    payload:   TemplatePayload::make('welcome_email')
                   ->variables(['name' => 'John', 'plan' => 'Pro'])
                   ->language('fa'),
    channel:   'email',
));
```

### Targeting a Specific Provider

```php
$notification = $notifier->send(new SendNotificationData(
    recipient:  '+989123456789',
    payload:    SmsPayload::fromMessage('Hello!'),
    providerId: 3,   // channel is inferred from the provider
));
```

### Adding Tags

```php
$notification = $notifier->send(new SendNotificationData(
    recipient: '+989123456789',
    payload:   SmsPayload::fromMessage('Promotion!'),
    channel:   'sms',
    tags:      ['marketing', 'summer-campaign'],
));
```

---

## Batch Notifications

```php
use Esanj\NotificationClient\DTOs\SendBatchNotificationData;
use Esanj\NotificationClient\DTOs\Payloads\SmsPayload;

$batch = $notifier->sendBatch(new SendBatchNotificationData(
    recipients: ['+989111111111', '+989222222222', '+989333333333'],
    payload:    SmsPayload::fromMessage('Hello everyone!'),
    channel:    'sms',
    priority:   'low',
    batchName:  'Summer Campaign 2025',
    tags:       ['marketing'],
));

echo $batch->uuid;                   // "batch-uuid"
echo $batch->totalNotifications;     // 3
echo $batch->progressPercentage();   // 0.0 (just queued)
```

---

## Querying Notifications

### List with filters

```php
use Esanj\NotificationClient\DTOs\NotificationFilter;

$result = $notifier->listNotifications(new NotificationFilter(
    perPage:    20,
    status:     'sent',
    recipients: ['+989123456789'],
));

foreach ($result->items as $notification) {
    echo $notification->uuid . ': ' . $notification->status . PHP_EOL;
}

echo "Page {$result->currentPage} of {$result->lastPage}, total: {$result->total}";
```

### Get single notification

```php
$notification = $notifier->getNotification('550e8400-e29b-41d4-a716-446655440000');

if ($notification->isSent()) {
    echo "Sent at: " . $notification->sentAt->toDateTimeString();
}
```

### Batches

```php
// List batches
$result = $notifier->listBatches(perPage: 10);

// Get single batch
$batch = $notifier->getBatch('batch-uuid');

echo $batch->progressPercentage() . '%';
echo $batch->isCompleted() ? 'Done' : 'In progress';
```

---

## Providers & Tags

```php
// List your configured providers
$providers = $notifier->listProviders();
foreach ($providers as $provider) {
    echo "{$provider->providerName} ({$provider->providerChannel})" . PHP_EOL;
}

// List available tags
$tags = $notifier->listTags(perPage: 50);
foreach ($tags->items as $tag) {
    echo "{$tag->name}: used {$tag->usedCount} times" . PHP_EOL;
}
```

---

## Error Handling

All exceptions extend `Esanj\NotificationClient\Exceptions\NotificationClientException`.

```php
use Esanj\NotificationClient\Exceptions\ApiException;
use Esanj\NotificationClient\Exceptions\AuthenticationException;
use Esanj\NotificationClient\Exceptions\NotificationClientException;

try {
    $notification = $notifier->send($data);
} catch (AuthenticationException $e) {
    // OAuth credentials are invalid or the service is unreachable
    Log::critical('Notification auth failed', ['error' => $e->getMessage()]);

} catch (ApiException $e) {
    if ($e->isValidationError()) {
        // $data was invalid — inspect field errors
        $errors = $e->getErrors(); // ['recipient' => ['The recipient format is invalid.']]
    }
    Log::error('Notification API error', [
        'status'   => $e->statusCode,
        'response' => $e->responseBody,
    ]);

} catch (NotificationClientException $e) {
    // Catch-all for any package exception
}
```

| Exception | When thrown |
|-----------|-------------|
| `AuthenticationException` | Cannot fetch/refresh OAuth token |
| `ApiException` | Non-retriable HTTP error (4xx, persistent 5xx) |
| `NotificationClientException` | Base class — all exceptions above extend this |

---

## Testing

The package integrates cleanly with Guzzle's `MockHandler`. In your feature tests:

```php
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use Esanj\NotificationClient\Auth\TokenManager;
use Esanj\NotificationClient\Http\ApiClient;
use Esanj\NotificationClient\NotificationClient;

$mock = new MockHandler([
    // 1st call: token endpoint
    new Response(200, [], json_encode([
        'access_token' => 'test-token',
        'token_type'   => 'Bearer',
        'expires_in'   => 3600,
    ])),
    // 2nd call: send notification
    new Response(202, [], json_encode([
        'data' => [
            'uuid'       => 'test-uuid',
            'status'     => 'pending',
            'channel'    => 'sms',
            'recipient'  => '+989123456789',
            'batch_uuid' => null,
            'sent_at'    => null,
            'created_at' => now()->toIso8601String(),
            'updated_at' => now()->toIso8601String(),
        ],
    ])),
]);

$client = new Client(['handler' => HandlerStack::create($mock)]);

// Build dependencies manually
$tokenManager = new TokenManager(
    httpClient:    $client,
    cache:         app(\Illuminate\Contracts\Cache\Repository::class),
    logger:        app(\Psr\Log\LoggerInterface::class),
    clientId:      'test-id',
    clientSecret:  'test-secret',
    tokenEndpoint: 'http://test/api/v1/oauth/token',
    cacheKey:      'test_token',
    bufferSeconds: 60,
);

$apiClient = new ApiClient(
    httpClient:    $client,
    tokenManager:  $tokenManager,
    logger:        app(\Psr\Log\LoggerInterface::class),
    baseUrl:       'http://test',
    retryAttempts: 3,
    retrySleepMs:  0,
);

$notifier = new NotificationClient($apiClient);
```

---

## Available Payload Classes

| Class | Channel | Factory |
|-------|---------|---------|
| `SmsPayload` | SMS | `SmsPayload::fromMessage('text')` |
| `SmsPatternPayload` | SMS | `SmsPatternPayload::make('key', ['var' => 'val'])` |
| `EmailPayload` | Email | `EmailPayload::make()->subject(...)->html(...)` |
| `PushPayload` | Push | `PushPayload::make()->title(...)->body(...)` |
| `TemplatePayload` | Any | `TemplatePayload::make('key')->variables([...])->language('fa')` |

---

## Resource Properties

### `NotificationResource`
| Property | Type | Description |
|----------|------|-------------|
| `uuid` | `string` | Unique notification identifier |
| `status` | `string` | `pending` \| `queued` \| `processing` \| `sent` \| `failed` \| `delivered` \| `undelivered` |
| `channel` | `string` | `sms` \| `email` \| `push` |
| `recipient` | `string` | Recipient address / token |
| `batchUuid` | `string\|null` | Parent batch UUID if sent as part of a batch |
| `sentAt` | `CarbonImmutable\|null` | When the message was sent |
| `createdAt` | `CarbonImmutable` | |
| `updatedAt` | `CarbonImmutable` | |

### `BatchResource`
| Property | Type | Description |
|----------|------|-------------|
| `uuid` | `string` | Unique batch identifier |
| `status` | `string` | `pending` \| `processing` \| `canceled` \| `completed` |
| `totalNotifications` | `int` | Number of notifications in the batch |
| `processedNotifications` | `int` | Notifications processed so far |
| `progressPercentage()` | `float` | Computed progress 0–100 |

---

## Documentation

For a complete, beginner-friendly, step-by-step walkthrough — installing, sending your first notification,
building **custom payloads**, swapping the client implementation, testing, and troubleshooting — see
**[docs/GUIDE.md](docs/GUIDE.md)**.

## Changelog

See [CHANGELOG.md](CHANGELOG.md) for release history.

## License

MIT — © Esanj
