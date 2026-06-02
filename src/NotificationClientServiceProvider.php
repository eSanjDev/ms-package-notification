<?php

namespace Esanj\NotificationClient;

use Esanj\NotificationClient\Auth\TokenManager;
use Esanj\NotificationClient\Contracts\NotificationClientInterface;
use Esanj\NotificationClient\Contracts\TokenManagerInterface;
use Esanj\NotificationClient\Http\ApiClient;
use GuzzleHttp\Client;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Support\ServiceProvider;
use Psr\Log\LoggerInterface;

class NotificationClientServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/esanj/notification.php', 'esanj.notification');

        $this->app->singleton(TokenManagerInterface::class, function ($app) {
            $config = $app['config']['esanj']['notification'];

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
            $config = $app['config']['esanj']['notification'];

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
            );

            return new NotificationClient($apiClient);
        });

        $this->app->alias(NotificationClientInterface::class, NotificationClient::class);
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
