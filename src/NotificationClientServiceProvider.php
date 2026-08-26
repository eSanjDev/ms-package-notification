<?php

namespace Esanj\NotificationClient;

use Esanj\NotificationClient\Auth\TokenManager;
use Esanj\NotificationClient\Contracts\NotificationClientInterface;
use Esanj\NotificationClient\Contracts\TokenManagerInterface;
use Esanj\NotificationClient\Exceptions\ConfigurationException;
use Esanj\NotificationClient\Http\ApiClient;
use GuzzleHttp\Client;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Support\ServiceProvider;
use Psr\Log\LoggerInterface;

class NotificationClientServiceProvider extends ServiceProvider
{
    private const REQUIRED_CONFIG = [
        'base_url'      => 'NOTIFICATION_SERVICE_URL',
        'client_id'     => 'NOTIFICATION_CLIENT_ID',
        'client_secret' => 'NOTIFICATION_CLIENT_SECRET',
    ];

    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/notification.php', 'esanj.notification');

        $this->app->singleton(TokenManagerInterface::class, function ($app) {
            $config = $this->validatedConfig($app['config']['esanj']['notification'] ?? []);

            $cacheStore = $config['token']['cache_store']
                ? $app['cache']->store($config['token']['cache_store'])
                : $app[CacheRepository::class];

            $logChannel = $config['logging']['channel'];
            $logger = $logChannel
                ? $app['log']->channel($logChannel)
                : $app[LoggerInterface::class];

            return new TokenManager(
                httpClient:     new Client(['timeout' => $config['timeout'], 'connect_timeout' => 10]),
                cache:          $cacheStore,
                logger:         $logger,
                clientId:       $config['client_id'],
                clientSecret:   $config['client_secret'],
                tokenEndpoint:  rtrim($config['base_url'], '/') . '/api/v1/oauth/token',
                cacheKey:       $config['token']['cache_key'],
                bufferSeconds:  (int) $config['token']['buffer_seconds'],
            );
        });

        $this->app->singleton(NotificationClientInterface::class, function ($app) {
            $config = $this->validatedConfig($app['config']['esanj']['notification'] ?? []);

            $logChannel = $config['logging']['channel'];
            $logger = $logChannel
                ? $app['log']->channel($logChannel)
                : $app[LoggerInterface::class];

            $apiClient = new ApiClient(
                httpClient:    new Client(['timeout' => $config['timeout'], 'connect_timeout' => 10]),
                tokenManager:  $app[TokenManagerInterface::class],
                logger:        $logger,
                baseUrl:       $config['base_url'],
                retryAttempts: (int) $config['retry']['attempts'],
                retrySleepMs:  (int) $config['retry']['sleep_ms'],
                idempotencySupported: (bool) ($config['idempotency']['enabled'] ?? false),
            );

            return new NotificationClient($apiClient);
        });

        $this->app->alias(NotificationClientInterface::class, NotificationClient::class);
    }

    private function validatedConfig(array $config): array
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
