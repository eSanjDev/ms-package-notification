# 📚 Esanj Notification Client — Complete Beginner's Guide

This guide assumes **you have never used this package before**. It explains everything in plain language, step by
step, with copy-paste code. If you can edit a `.env` file and write a few lines of PHP, you can follow along.

> 💡 This package is a **client**. It does not send SMS/email/push itself — it talks (over HTTP) to the **Esanj
> Notification microservice**, which does the actual sending. Think of this package as a friendly remote control
> for that service.

---

## Table of Contents

1. [What this package does](#1-what-this-package-does)
2. [How it works (the big picture)](#2-how-it-works-the-big-picture)
3. [Installation](#3-installation)
4. [Configuration & `.env`](#4-configuration--env)
5. [Two ways to call the client](#5-two-ways-to-call-the-client)
6. [Your first notification (SMS)](#6-your-first-notification-sms)
7. [Sending each channel](#7-sending-each-channel)
8. [Choosing a provider, priority, tags & options](#8-choosing-a-provider-priority-tags--options)
9. [Sending in bulk (batches)](#9-sending-in-bulk-batches)
10. [Reading notifications back (querying & paging)](#10-reading-notifications-back-querying--paging)
11. [Providers & tags](#11-providers--tags)
12. [Handling errors safely](#12-handling-errors-safely)
13. [Recipe: Create your own custom payload](#13-recipe-create-your-own-custom-payload)
14. [Recipe: Replace or wrap the client](#14-recipe-replace-or-wrap-the-client)
15. [Testing without hitting the real service](#15-testing-without-hitting-the-real-service)
16. [Configuration reference](#16-configuration-reference)
17. [API endpoints used](#17-api-endpoints-used)
18. [Troubleshooting](#18-troubleshooting)
19. [Cheat sheet](#19-cheat-sheet)

---

## 1. What this package does

Once installed and configured, it lets your Laravel app:

- **Send notifications** — SMS, email, or push — with a single method call.
- **Send in bulk** — one call to up to 5,000 recipients (a "batch").
- **Check status** — look up a notification or batch later to see if it was sent/failed.
- **List** your providers and tags.

It also handles the boring, error-prone parts for you: logging in to the service (OAuth tokens), caching that
login, refreshing it when it expires (once across all your processes, behind a cache lock), and retrying failed
requests.

---

## 2. How it works (the big picture)

You only ever touch a few things. Here's the whole package in one table:

| You use…            | What it is                                                        |
|---------------------|------------------------------------------------------------------|
| **`Notifier` / `NotificationClientInterface`** | The main entry point. You call `send()`, `sendBatch()`, etc. on it. |
| **Payload classes** | Describe *what* to send (`SmsPayload`, `EmailPayload`, `PushPayload`, `SmsPatternPayload`, `TemplatePayload`). |
| **`SendNotificationData`** | Wraps the payload + *who* to send to + options, and hands it to the client. |
| **Resource classes** | The typed objects you get **back** (`NotificationResource`, `BatchResource`, etc.). |

Behind the scenes (you normally never touch these):

| Hidden part      | Job                                                                          |
|------------------|------------------------------------------------------------------------------|
| `TokenManager`   | Logs in to the service, caches the access token, refreshes it when expired.  |
| `ApiClient`      | Makes the HTTP calls, adds the token header, retries on failure.             |
| `Token`          | A small value object holding the access token and its expiry.                |

**Mental model of one send:**

```
Your code → SendNotificationData(payload) → Notifier::send()
          → ApiClient (adds token, POSTs JSON) → Notification microservice
          → you get back a NotificationResource ($notification->uuid, ->status, ...)
```

---

## 3. Installation

In your project root:

```bash
composer require esanj/notification-client
```

Publish the config file:

```bash
php artisan vendor:publish --tag=notification-config
```

This creates `config/esanj/notification.php`. Laravel auto-registers the package and the `Notifier` facade — no
manual setup needed.

> ✅ **Requirements:** PHP 8.2+ and Laravel 12 or 13.

---

## 4. Configuration & `.env`

You **must** set three values. Add them to your `.env` (ask your Esanj admin for the values):

```env
NOTIFICATION_SERVICE_URL=https://notification.your-domain.com
NOTIFICATION_CLIENT_ID=your-client-id
NOTIFICATION_CLIENT_SECRET=your-client-secret
```

Everything else is optional and has sensible defaults:

```env
# Optional
NOTIFICATION_TOKEN_CACHE_STORE=redis        # default: your app's default cache store
NOTIFICATION_TOKEN_CACHE_KEY=notif_token    # default: esanj_notification_access_token
NOTIFICATION_LOG_CHANNEL=stack              # default: your app's default log channel
```

> ⚠️ If you change config, run `php artisan config:clear` so Laravel picks it up.

See the [Configuration reference](#16-configuration-reference) for every option.

---

## 5. Two ways to call the client

Pick whichever you prefer — they do exactly the same thing.

**A) Dependency Injection (recommended).** Type-hint the interface and Laravel hands you a ready-to-use client:

```php
use Esanj\NotificationClient\Contracts\NotificationClientInterface;

class OrderService
{
    public function __construct(
        private readonly NotificationClientInterface $notifier
    ) {}

    public function notifyCustomer(): void
    {
        $this->notifier->send(/* ... */);
    }
}
```

**B) Facade.** Quick and global — no constructor needed:

```php
use Esanj\NotificationClient\Facades\Notifier;

Notifier::send(/* ... */);
```

In the examples below, `$notifier` means "either an injected `NotificationClientInterface`, or the `Notifier`
facade." They are interchangeable.

---

## 6. Your first notification (SMS)

The simplest possible send — a plain SMS:

```php
use Esanj\NotificationClient\DTOs\SendNotificationData;
use Esanj\NotificationClient\DTOs\Payloads\SmsPayload;

$notification = $notifier->send(new SendNotificationData(
    recipient: '+989123456789',
    payload:   SmsPayload::fromMessage('Your OTP is 1234'),
    channel:   'sms',
));

echo $notification->uuid;    // e.g. "550e8400-e29b-41d4-..."
echo $notification->status;  // e.g. "pending"
```

**What just happened:**
- `SmsPayload::fromMessage(...)` = *what* to send.
- `SendNotificationData(...)` = the *who* (`recipient`), the *what* (`payload`), and the *how* (`channel`).
- `send(...)` returns a `NotificationResource` — a typed object with the new notification's `uuid` and `status`.

> 📌 Every `SendNotificationData` needs **either** a `channel` (`'sms'`, `'email'`, `'push'`) **or** a
> `providerId`. See [section 8](#8-choosing-a-provider-priority-tags--options).

---

## 7. Sending each channel

### SMS — pattern / template code (server-side template)

Use this when the service stores the SMS text and you only supply variables:

```php
use Esanj\NotificationClient\DTOs\Payloads\SmsPatternPayload;

$notifier->send(new SendNotificationData(
    recipient: '+989123456789',
    payload:   SmsPatternPayload::make('otp_pattern', ['code' => '1234', 'name' => 'John']),
    channel:   'sms',
));
```

### Email

`EmailPayload` is a **builder** — chain the parts you need. Only `make()` is required; everything else is optional:

```php
use Esanj\NotificationClient\DTOs\Payloads\EmailPayload;

$notifier->send(new SendNotificationData(
    recipient: 'user@example.com',
    payload:   EmailPayload::make()
                   ->subject('Welcome to our platform')
                   ->html('<h1>Hello, John!</h1><p>Your account is ready.</p>')
                   ->text('Hello, John! Your account is ready.')   // plain-text fallback
                   ->from('no-reply@example.com', 'Example')
                   ->replyTo('support@example.com')
                   ->cc(['manager@example.com'])
                   ->bcc(['archive@example.com']),
    channel:   'email',
));
```

### Push

```php
use Esanj\NotificationClient\DTOs\Payloads\PushPayload;

$notifier->send(new SendNotificationData(
    recipient: 'device-fcm-token',
    payload:   PushPayload::make()
                   ->title('New Order')
                   ->body('Your order #1234 has been confirmed.')
                   ->url('https://app.example.com/orders/1234')
                   ->data(['order_id' => 1234]),   // extra key/value data
    channel:   'push',
));
```

### Template (works for any channel)

Use a template stored on the service, with variables and an optional language:

```php
use Esanj\NotificationClient\DTOs\Payloads\TemplatePayload;

$notifier->send(new SendNotificationData(
    recipient: 'user@example.com',
    payload:   TemplatePayload::make('welcome_email')
                   ->variables(['name' => 'John', 'plan' => 'Pro'])
                   ->language('fa'),
    channel:   'email',
));
```

> 💡 **Builders are immutable.** Each method (`->subject()`, `->html()`, …) returns a **new copy**. So
> `$p = EmailPayload::make(); $p->subject('Hi');` on its own does nothing — you must keep the returned value:
> `$p = $p->subject('Hi');` (chaining, as shown above, already does this correctly).

---

## 8. Choosing a provider, priority, tags & options

`SendNotificationData` accepts more than just recipient/payload/channel:

| Parameter    | Type            | Default      | Meaning                                                            |
|--------------|-----------------|--------------|--------------------------------------------------------------------|
| `recipient`  | `string`        | *(required)* | Phone / email / device token.                                      |
| `payload`    | `PayloadInterface` | *(required)* | One of the payload objects.                                     |
| `channel`    | `string\|null`  | `null`       | `'sms'`, `'email'`, or `'push'`. **Required unless** `providerId` is set. |
| `providerId` | `int\|null`     | `null`       | Send through a specific provider. Channel is inferred from it.     |
| `priority`   | `string`        | `'medium'`   | `'low'`, `'medium'`, or `'high'`.                                  |
| `tags`       | `string[]`      | `[]`         | Tag names to attach (the tags must already exist on the service). |
| `options`    | `array`         | `[]`         | Extra options, e.g. `['lock_provider' => true]`.                  |
| `idempotencyKey` | `string\|null` | `null`      | Stable key that collapses a repeated send into the original one.  |

**Target a specific provider** (channel is inferred, so you can omit it):

```php
$notifier->send(new SendNotificationData(
    recipient:  '+989123456789',
    payload:    SmsPayload::fromMessage('Hello!'),
    providerId: 3,
));
```

**Set priority and tags:**

```php
$notifier->send(new SendNotificationData(
    recipient: '+989123456789',
    payload:   SmsPayload::fromMessage('Promotion!'),
    channel:   'sms',
    priority:  'high',
    tags:      ['marketing', 'summer-campaign'],
));
```

**Pass extra options** (for example, force the message to stay on the chosen provider):

```php
$notifier->send(new SendNotificationData(
    recipient:  '+989123456789',
    payload:    SmsPayload::fromMessage('Hello!'),
    providerId: 3,
    options:    ['lock_provider' => true],
));
```

---

## 9. Sending in bulk (batches)

Send the same payload to many recipients in one call:

```php
use Esanj\NotificationClient\DTOs\SendBatchNotificationData;
use Esanj\NotificationClient\DTOs\Payloads\SmsPayload;

$batch = $notifier->sendBatch(new SendBatchNotificationData(
    recipients: ['+989111111111', '+989222222222', '+989333333333'], // up to 5000
    payload:    SmsPayload::fromMessage('Hello everyone!'),
    channel:    'sms',
    priority:   'low',                  // batches default to 'low'
    batchName:  'Summer Campaign 2025', // optional label
    tags:       ['marketing'],
));

echo $batch->uuid;                  // the batch id — keep it to check progress later
echo $batch->totalNotifications;    // 3
echo $batch->progressPercentage();  // 0.0 right after queueing
```

`sendBatch` returns a `BatchResource`. Check on it later with `getBatch($uuid)` (see next section).

---

## 10. Reading notifications back (querying & paging)

### Get one notification

```php
$notification = $notifier->getNotification('550e8400-e29b-41d4-a716-446655440000');

if ($notification->isSent()) {
    echo 'Sent at: ' . $notification->sentAt->toDateTimeString();
}

// Status helpers available on NotificationResource:
$notification->isSent();     // status === 'sent'
$notification->isFailed();   // status === 'failed'
$notification->isPending();  // status is pending | queued | processing
```

### List notifications with filters

```php
use Esanj\NotificationClient\DTOs\NotificationFilter;

$result = $notifier->listNotifications(new NotificationFilter(
    perPage:    20,
    status:     'sent',                  // optional
    recipients: ['+989123456789'],       // optional
    page:       1,                       // optional, defaults to 1
));

foreach ($result->items as $notification) {
    echo $notification->uuid . ': ' . $notification->status . PHP_EOL;
}

echo "Page {$result->currentPage} of {$result->lastPage}, total: {$result->total}";
```

`listNotifications`, `listBatches`, and `listTags` all return a **`PaginatedResult`** with these properties:
`items`, `total`, `perPage`, `currentPage`, `lastPage`, `from`, `to`, plus `hasMorePages()`, `nextPage()` and
`isEmpty()`. It is also iterable and countable, so you can `foreach ($result as $item)` and `count($result)`
directly — `count()` is the size of *this page*, `$result->total` the size of everything.

**Loop through every page** — `eachNotification()` fetches one page at a time and yields the items lazily, so
memory stays flat no matter how many records there are:

```php
foreach ($notifier->eachNotification(new NotificationFilter(perPage: 50, status: 'failed')) as $n) {
    // process $n — pages are fetched as you go
}
```

If you'd rather drive the loop yourself, ask for pages explicitly. The page number is what moves you forward —
without it you re-fetch page 1 forever:

```php
$filter = new NotificationFilter(perPage: 50);

do {
    $result = $notifier->listNotifications($filter);

    foreach ($result as $n) {
        // process $n
    }

    $filter = $filter->nextPage();      // same filter, next page
} while ($result->hasMorePages());
```

`listBatches()` and `listTags()` take the page as a second argument: `listTags(perPage: 50, page: 2)`.

### Batches

```php
$result = $notifier->listBatches(perPage: 10);   // PaginatedResult of BatchResource

$batch = $notifier->getBatch('batch-uuid');
echo $batch->progressPercentage() . '%';
echo $batch->isCompleted() ? 'Done' : 'In progress';
```

---

## 11. Providers & tags

```php
// Providers — a plain array of ProviderResource holding *every* provider
foreach ($notifier->listProviders() as $provider) {
    echo "{$provider->providerName} ({$provider->providerChannel})" . PHP_EOL;
}
$one = $notifier->getProvider(3);

// Need the pagination metadata, or just one page? Ask for a page instead.
$page = $notifier->listProvidersPage(perPage: 25, page: 2);   // PaginatedResult
echo "{$page->total} providers in total";

// Tags — returns a PaginatedResult of TagResource
$tags = $notifier->listTags(perPage: 50);
foreach ($tags->items as $tag) {
    echo "{$tag->name}: used {$tag->usedCount} times" . PHP_EOL;
}
$tag = $notifier->getTag(7);
```

> ℹ️ The providers endpoint **is** paginated server-side (`per_page` defaults to 15, capped at 100).
> `listProviders()` walks the pages for you and returns the complete list, so it costs one request per 100
> providers — normally one. Use `listProvidersPage()` when you want the metadata or control over paging.

---

## 12. Handling errors safely

Every exception this package throws extends `NotificationClientException`, so you can catch broadly or narrowly.

```php
use Esanj\NotificationClient\Exceptions\ApiException;
use Esanj\NotificationClient\Exceptions\AuthenticationException;
use Esanj\NotificationClient\Exceptions\NotificationClientException;
use Esanj\NotificationClient\Exceptions\RateLimitException;

try {
    $notification = $notifier->send($data);

} catch (RateLimitException $e) {
    // The token endpoint is throttled — your credentials are fine, you're just fetching tokens too often
    $this->release($e->retryAfter ?? 60);

} catch (AuthenticationException $e) {
    // Could not log in / refresh the token (bad client id/secret, or service unreachable)
    report($e);

} catch (ApiException $e) {
    if ($e->isValidationError()) {          // HTTP 422
        $errors = $e->getErrors();          // e.g. ['recipient' => ['The recipient format is invalid.']]
    }
    if ($e->isRateLimited()) {              // HTTP 429, still throttled after backing off
        $this->release($e->retryAfter ?? 60);
    }
    // Always available on ApiException:
    $e->statusCode;     // int  — e.g. 422, 404, 500, or 0 for connection errors
    $e->responseBody;   // array — the decoded JSON error body
    $e->retryAfter;     // ?int — seconds from the `Retry-After` header, on a 429
    $e->isUnauthorized();   // 401 — the token was rejected
    $e->isForbidden();      // 403 — the token is fine, this client lacks the service permission
    report($e);

} catch (NotificationClientException $e) {
    // Catch-all safety net for anything else from the package
    report($e);
}
```

| Exception                     | When it's thrown                                                       |
|-------------------------------|------------------------------------------------------------------------|
| `AuthenticationException`     | The token could not be fetched or refreshed.                           |
| `RateLimitException`          | The token endpoint answered `429`. Not a credentials problem — see the note below. |
| `ApiException`                | The API returned an error (validation 4xx, a `429` that outlived the back-off, a 5xx after all retries, or a connection failure). |
| `NotificationClientException` | Base class — all of the above extend it.                               |

> ⏳ **`429` is handled for you, once.** On a rate-limited response the client waits for the server's `Retry-After`
> (capped at 30 seconds so a web request can't hang) and replays the call — for sends too, since the throttle
> middleware rejects the request before anything is created. If it's *still* throttled when attempts run out, you get
> an `ApiException` with `isRateLimited()` true and `$e->retryAfter` set.

> 🔑 **`RateLimitException` on the token endpoint means one thing: too many token fetches.** That endpoint allows
> 10 requests per minute per IP. If you hit it, your processes are not sharing the cached token — check that every
> web process and queue worker points `token.cache_store` at the same shared store (Redis, Memcached), not `array`
> or a per-container `file` cache.

> 🔁 **Reads retry themselves.** `GET` calls are retried `retry.attempts` times on `5xx` and connection errors. A
> `401` on any method refreshes the token and replays the call once. An exception is thrown once retries are
> exhausted (or immediately for non-retryable errors like `422`).

> 🚫 **A `403` is never retried.** The service returns it from the `service.permission` middleware: your token is
> valid, this client just has no permission for that endpoint. Refreshing the token would throw away a healthy one,
> burn the `/oauth/token` rate limit and force every parallel request to re-authenticate — all while the answer stays
> `403`. Handle it as the configuration problem it is:
>
> ```php
> catch (ApiException $e) {
>     if ($e->isForbidden()) {
>         Log::critical('Notification client lacks the required service permission', [
>             'response' => $e->responseBody,
>         ]);
>     }
> }
> ```
>
> The fix is on the service: grant this `client_id` the permission (e.g. `tags_list`, `send_single_notification`) in
> `config/esanj/app_service.php`.

> ⚠️ **`send()` and `sendBatch()` are not retried by default.** The service registers the notification and queues it
> before it replies, so a timed-out or `5xx` send may well have gone through — replaying it would deliver the message
> twice and bill you twice. The client fails fast instead. To make sends retryable, enable idempotency:
>
> ```php
> // config/esanj/notification.php
> 'idempotency' => ['enabled' => env('NOTIFICATION_IDEMPOTENCY', false)],
> ```
>
> Turn this on **only** when the service honours the `Idempotency-Key` header and returns the original response for a
> repeated key; otherwise retries duplicate messages exactly as before. Once enabled, the client generates a key per
> send and reuses it across that send's retries.
>
> When your own application can issue the same send twice (a retried queue job, a double-submitted form), pass a
> stable `idempotencyKey` on `SendNotificationData` / `SendBatchNotificationData` so both attempts collapse into one
> notification:
>
> ```php
> new SendNotificationData(
>     recipient:      $user->mobile,
>     payload:        SmsPayload::fromMessage("Your code is {$otp->code}"),
>     channel:        'sms',
>     idempotencyKey: "otp:{$user->id}:{$otp->id}",
> );
> ```

---

## 13. Recipe: Create your own custom payload

This is the equivalent of "adding a new page" for this package. Every payload is just a class that implements one
tiny interface:

```php
namespace Esanj\NotificationClient\Contracts;

interface PayloadInterface
{
    public function toArray(): array;   // returns the JSON body the service expects
}
```

So to support a payload shape the built-in classes don't cover, write your own. Example — a hypothetical "voice
call" payload:

**Step 1 — create the class** (anywhere in your app, e.g. `app/Notifications/Payloads/VoicePayload.php`):

```php
namespace App\Notifications\Payloads;

use Esanj\NotificationClient\Contracts\PayloadInterface;

final class VoicePayload implements PayloadInterface
{
    private function __construct(
        private readonly string $text,
        private readonly int $repeat,
    ) {}

    public static function make(string $text, int $repeat = 1): self
    {
        return new self($text, $repeat);
    }

    public function toArray(): array
    {
        // Must match exactly what the microservice expects for this channel.
        return [
            'voice' => [
                'text'   => $this->text,
                'repeat' => $this->repeat,
            ],
        ];
    }
}
```

**Step 2 — use it like any built-in payload:**

```php
use App\Notifications\Payloads\VoicePayload;

$notifier->send(new SendNotificationData(
    recipient: '+989123456789',
    payload:   VoicePayload::make('Your code is 1234', repeat: 2),
    channel:   'voice',
));
```

That's it. Because `SendNotificationData` accepts **any** `PayloadInterface`, your class plugs straight in.

> ✅ **Tip — copy the closest built-in.** Look at `src/DTOs/Payloads/` for a class similar to what you need
> (`SmsPayload` for a simple value, `EmailPayload` for a fluent builder) and adapt its `toArray()` shape.

---

## 14. Recipe: Replace or wrap the client

Sometimes you want to change how the client behaves globally — for example, to add logging around every send, or
to swap in a fake during local development. Because the client is bound to an **interface** in the container, you
can rebind it.

**Wrap the real client** (decorator) — put this in a service provider's `register()`:

```php
use Esanj\NotificationClient\Contracts\NotificationClientInterface;

$this->app->extend(NotificationClientInterface::class, function ($client, $app) {
    return new LoggingNotificationClient($client); // your class implementing NotificationClientInterface
});
```

**Replace it entirely** (e.g. a fake that records calls in tests):

```php
$this->app->singleton(NotificationClientInterface::class, fn () => new FakeNotificationClient());
```

Any code that injects `NotificationClientInterface` or uses the `Notifier` facade now gets your version — no other
changes needed.

---

## 15. Testing without hitting the real service

The package is built on Guzzle, so you can feed it a `MockHandler` and assert behavior without any network calls.
Build the objects by hand with the same constructor arguments the service provider uses:

```php
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use Esanj\NotificationClient\Auth\TokenManager;
use Esanj\NotificationClient\Http\ApiClient;
use Esanj\NotificationClient\NotificationClient;

$mock = new MockHandler([
    // 1st HTTP call the client makes: fetch the token
    new Response(200, [], json_encode([
        'access_token' => 'test-token',
        'token_type'   => 'Bearer',
        'expires_in'   => 3600,
    ])),
    // 2nd call: the actual send
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
    retrySleepMs:  0,   // no real sleeping in tests
);

$notifier = new NotificationClient($apiClient);

$result = $notifier->send(/* ... */);
// assert on $result->uuid, $result->status, ...
```

> 💡 For most app-level tests, the easier path is [section 14](#14-recipe-replace-or-wrap-the-client): bind a
> `FakeNotificationClient` and assert what your code asked it to send.

---

## 16. Configuration reference

File: `config/esanj/notification.php`. Internally read via the key `esanj.notification`.

| Key                      | Env variable                       | Default                              | What it does                                                  |
|--------------------------|------------------------------------|--------------------------------------|--------------------------------------------------------------|
| `base_url`               | `NOTIFICATION_SERVICE_URL`         | `http://localhost`                   | Base URL of the notification microservice.                   |
| `client_id`              | `NOTIFICATION_CLIENT_ID`           | *(none)*                             | OAuth client id.                                             |
| `client_secret`          | `NOTIFICATION_CLIENT_SECRET`       | *(none)*                             | OAuth client secret.                                        |
| `token.cache_store`      | `NOTIFICATION_TOKEN_CACHE_STORE`   | `null` → app default store           | Which cache store holds the access token. **Must be shared by every process** — see below. |
| `token.cache_key`        | `NOTIFICATION_TOKEN_CACHE_KEY`     | `esanj_notification_access_token`    | Cache key for the token.                                     |
| `token.buffer_seconds`   | —                                  | `60`                                 | Refresh the token this many seconds **before** it expires.   |
| `retry.attempts`         | —                                  | `3`                                  | Total attempts per retryable request (`1` = no retry).       |
| `retry.sleep_ms`         | —                                  | `1000`                               | Milliseconds to wait between retries.                        |
| `idempotency.enabled`    | `NOTIFICATION_IDEMPOTENCY`         | `false`                              | Service honours `Idempotency-Key`; makes sends retryable.    |
| `timeout`                | —                                  | `30`                                 | HTTP request timeout, in seconds.                            |
| `logging.channel`        | `NOTIFICATION_LOG_CHANNEL`         | `null` → app default channel         | Log channel for the package's warnings/errors.               |

> To change `retry`, `timeout`, or `buffer_seconds`, edit `config/esanj/notification.php` directly (these have no
> env shortcuts), then run `php artisan config:clear`.

> 🔐 **Point `token.cache_store` at a shared store — `redis` or `memcached`.** Two things depend on it. The token
> itself is shared, so ten workers use one login instead of ten. And the refresh runs behind that store's atomic
> lock, so when the cache goes cold (a deploy, a Redis restart, an `invalidate()`) exactly one process calls the
> token endpoint while the rest wait and then read its result.
>
> The token endpoint allows **10 requests per minute per IP**. With twenty queue workers and a per-container store
> (`file`, `array`), a cold start means twenty simultaneous logins: ten succeed, ten get a `429`, those jobs fail and
> the queue retries them — a self-inflicted outage. With one shared store it is a single request roughly once an
> hour. A store with no lock support still works; it just loses the stampede protection.

---

## 17. API endpoints used

For reference, here's exactly which microservice endpoints each method calls:

| Method                   | HTTP   | Endpoint                                  |
|--------------------------|--------|-------------------------------------------|
| *(token fetch)*          | `POST` | `/api/v1/oauth/token`                     |
| `send`                   | `POST` | `/api/v1/send`                            |
| `sendBatch`              | `POST` | `/api/v1/send-batch`                      |
| `getNotification`        | `GET`  | `/api/v1/notifications/{uuid}`            |
| `listNotifications`      | `GET`  | `/api/v1/notifications`                   |
| `eachNotification`       | `GET`  | `/api/v1/notifications` (one call per page) |
| `getBatch`               | `GET`  | `/api/v1/notification-batches/{uuid}`     |
| `listBatches`            | `GET`  | `/api/v1/notification-batches`            |
| `listProviders`          | `GET`  | `/api/v1/client-providers` (one call per page) |
| `listProvidersPage`      | `GET`  | `/api/v1/client-providers`                |
| `getProvider`            | `GET`  | `/api/v1/client-providers/{id}`           |
| `listTags`               | `GET`  | `/api/v1/tags`                            |
| `getTag`                 | `GET`  | `/api/v1/tags/{id}`                       |

---

## 18. Troubleshooting

**`AuthenticationException: Could not authenticate...`**
Your `NOTIFICATION_CLIENT_ID` / `NOTIFICATION_CLIENT_SECRET` are wrong, or `NOTIFICATION_SERVICE_URL` is
unreachable. Double-check `.env`, then `php artisan config:clear`.

**`AuthenticationException: Timed out waiting for another process to refresh the access token.`**
Another process held the token lock for more than 10 seconds and never published a token — usually the token
endpoint itself is slow or down. Check that the service is reachable and look for the token errors it logged; the
process that held the lock recorded the real cause.

**Changes to `.env` or config seem ignored.**
Laravel caches config. Run `php artisan config:clear` (and `php artisan config:cache` again if you cache config in
production).

**`ApiException` with `isValidationError()` true (HTTP 422).**
The service rejected your data. Inspect `$e->getErrors()` — it returns a field-by-field error map and usually tells
you exactly what's wrong (bad recipient format, missing channel, unknown tag, etc.).

**`RateLimitException: Token endpoint rate limit reached...`**
Every process is fetching its own access token instead of reading the shared one. Point
`token.cache_store` at a store all of them can see (Redis/Memcached) and confirm they use the same `cache_key`. The
limit is 10 token requests per minute per IP; one shared token means roughly one request per hour.

**`ApiException` with `isForbidden()` true (HTTP 403).**
Your credentials are fine — this client has no permission for that endpoint on the service. Nothing on the client
side fixes it: grant the permission to your `client_id` in the service's `config/esanj/app_service.php`
(`tags_list`, `send_single_notification`, `providers_list`, …). The client fails fast here on purpose and does not
refresh the token.

**My SMS/email never arrives, but `send()` succeeded.**
`send()` returning `status: 'pending'` only means the service **accepted** it for delivery. Check the real outcome
later with `getNotification($uuid)` and `isSent()` / `isFailed()`.

**"I set a value on a payload builder but it didn't apply."**
Payload builders are immutable — each method returns a new object. Always keep the return value (use chaining), see
the tip in [section 7](#7-sending-each-channel).

**Requests are slow when the service is down.**
That's the retry policy working. Lower `retry.attempts` and/or `retry.sleep_ms` in the config if you prefer to fail
faster, or raise `timeout` if the service is just slow.

---

## 19. Cheat sheet

```php
// Send (pick a payload, wrap in SendNotificationData, call send)
$notifier->send(new SendNotificationData(
    recipient: '+989123456789',
    payload:   SmsPayload::fromMessage('Hi'),
    channel:   'sms',
));

// Batch
$notifier->sendBatch(new SendBatchNotificationData(
    recipients: ['+98911...', '+98922...'],
    payload:    SmsPayload::fromMessage('Hi all'),
    channel:    'sms',
));

// Look up
$notifier->getNotification($uuid);
$notifier->getBatch($batchUuid);
$notifier->listNotifications(new NotificationFilter(status: 'sent', page: 2));
foreach ($notifier->eachNotification() as $n) { /* every page, lazily */ }

// Meta
$notifier->listProviders();                             // all of them
$notifier->listProvidersPage(perPage: 25, page: 2);     // one page + metadata
$notifier->listTags(perPage: 50, page: 1);
```

| Payload class       | Channel | How to build                                                            |
|---------------------|---------|-------------------------------------------------------------------------|
| `SmsPayload`        | SMS     | `SmsPayload::fromMessage('text')`                                       |
| `SmsPatternPayload` | SMS     | `SmsPatternPayload::make('key', ['var' => 'val'])`                      |
| `EmailPayload`      | Email   | `EmailPayload::make()->subject(...)->html(...)->text(...)`              |
| `PushPayload`       | Push    | `PushPayload::make()->title(...)->body(...)->url(...)->data([...])`     |
| `TemplatePayload`   | Any     | `TemplatePayload::make('key')->variables([...])->language('fa')`        |
| *your own*          | Any     | implement `PayloadInterface::toArray()` — see [section 13](#13-recipe-create-your-own-custom-payload) |

```bash
# Common commands
composer require esanj/notification-client
php artisan vendor:publish --tag=notification-config
php artisan config:clear
```

---

Need the quick reference instead? See the [README](../README.md).