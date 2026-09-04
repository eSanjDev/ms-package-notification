<?php

namespace Esanj\NotificationClient;

use Esanj\NotificationClient\Auth\TokenManager;
use Esanj\NotificationClient\Contracts\NotificationClientInterface;
use Esanj\NotificationClient\Contracts\TokenManagerInterface;
use Esanj\NotificationClient\Exceptions\ConfigurationException;
use Esanj\NotificationClient\Http\ApiClient;
use GuzzleHttp\Client;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Contracts\Encryption\Encrypter;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;
use Psr\Log\LoggerInterface;

class NotificationClientServiceProvider extends ServiceProvider
{
    private const REQUIRED_CONFIG = [
        'base_url'      => 'NOTIFICATION_SERVICE_URL',
        'client_id'     => 'NOTIFICATION_CLIENT_ID',
        'client_secret' => 'NOTIFICATION_CLIENT_SECRET',
    ];

    private ?array $config = null;

    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/notification.php', 'esanj.notification');

        $this->app->singleton(TokenManagerInterface::class, function ($app) {
            $config = $this->config($app);

            $cacheStore = $config['token']['cache_store']
                ? $app['cache']->store($config['token']['cache_store'])
                : $app[CacheRepository::class];

            return new TokenManager(
                httpClient:     $this->httpClient($config),
                cache:          $cacheStore,
                logger:         $this->logger($app, $config),
                clientId:       $config['client_id'],
                clientSecret:   $config['client_secret'],
                tokenEndpoint:  rtrim($config['base_url'], '/') . '/api/v1/oauth/token',
                cacheKey:       $config['token']['cache_key'],
                bufferSeconds:  (int) $config['token']['buffer_seconds'],
                encrypter:      ($config['token']['encrypt'] ?? false) ? $app[Encrypter::class] : null,
                httpTimeoutSeconds: (int) $config['timeout'],
            );
        });

        $this->app->singleton(NotificationClientInterface::class, function ($app) {
            $config = $this->config($app);

            $apiClient = new ApiClient(
                httpClient:    $this->httpClient($config),
                tokenManager:  $app[TokenManagerInterface::class],
                logger:        $this->logger($app, $config),
                baseUrl:       $config['base_url'],
                retryAttempts: (int) $config['retry']['attempts'],
                retrySleepMs:  (int) $config['retry']['sleep_ms'],
                idempotencySupported: (bool) ($config['idempotency']['enabled'] ?? false),
            );

            return new NotificationClient($apiClient);
        });

        $this->app->alias(NotificationClientInterface::class, NotificationClient::class);
    }

    private function config(Application $app): array
    {
        return $this->config ??= $this->validate($app['config']['esanj']['notification'] ?? []);
    }

    private function httpClient(array $config): Client
    {
        return new Client([
            'timeout'         => (int) $config['timeout'],
            'connect_timeout' => (int) ($config['connect_timeout'] ?? 10),
        ]);
    }

    private function logger(Application $app, array $config): LoggerInterface
    {
        $channel = $config['logging']['channel'] ?? null;

        return $channel ? $app['log']->channel($channel) : $app[LoggerInterface::class];
    }

    private function validate(array $config): array
    {
        foreach (self::REQUIRED_CONFIG as $key => $envVar) {
            if (blank($config[$key] ?? null)) {
                throw new ConfigurationException(sprintf(
                    'Notification client is not configured: "%s" is empty. Set %s in your .env file, '
                    . 'then run `php artisan config:clear`.',
                    $key,
                    $envVar,
                ));
            }
        }

        $url = $config['base_url'];

        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            throw new ConfigurationException(
                "Invalid NOTIFICATION_SERVICE_URL: [{$url}]. It must be a full URL, e.g. https://notification.example.com."
            );
        }

        if ($this->app->environment('production') && !str_starts_with($url, 'https://')) {
            throw new ConfigurationException(
                "NOTIFICATION_SERVICE_URL must use HTTPS in production; got [{$url}]. "
                . 'Sending client_secret over plain HTTP exposes it to anyone on the network path.'
            );
        }

        return $config;
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../config/notification.php' => config_path('esanj/notification.php'),
            ], 'notification-config');
        }
    }
}
