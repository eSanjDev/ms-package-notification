<?php

namespace Esanj\NotificationClient\Auth;

use Esanj\NotificationClient\Contracts\TokenManagerInterface;
use Esanj\NotificationClient\Exceptions\AuthenticationException;
use Esanj\NotificationClient\Exceptions\RateLimitException;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Contracts\Cache\LockProvider;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Contracts\Encryption\Encrypter;
use Psr\Log\LoggerInterface;
use Throwable;

class TokenManager implements TokenManagerInterface
{
    private const LOCK_GRACE_SECONDS = 5;

    private const LOCK_WAIT_SECONDS = 10;

    private const REFRESH_STORM_THRESHOLD = 3;

    private const REFRESH_STORM_WINDOW = 60;

    private ?Token $runtimeToken = null;

    private array $recentRefreshes = [];

    public function __construct(
        private readonly Client $httpClient,
        private readonly CacheRepository $cache,
        private readonly LoggerInterface $logger,
        private readonly string $clientId,
        private readonly string $clientSecret,
        private readonly string $tokenEndpoint,
        private readonly string $cacheKey,
        private readonly int $bufferSeconds,
        private readonly ?Encrypter $encrypter = null,
        private readonly int $httpTimeoutSeconds = 30,
    ) {}

    public function getToken(): Token
    {
        if ($this->runtimeToken !== null && !$this->runtimeToken->isExpired()) {
            return $this->runtimeToken;
        }

        return $this->cachedToken() ?? $this->refresh();
    }

    public function refresh(): Token
    {
        $store = $this->cache->getStore();

        if (!$store instanceof LockProvider) {
            return $this->fetchToken();
        }

        $lock = $store->lock(
            $this->cacheKey . ':lock',
            max(1, $this->httpTimeoutSeconds) + self::LOCK_GRACE_SECONDS,
        );

        try {
            return $lock->block(
                self::LOCK_WAIT_SECONDS,
                fn(): Token => $this->cachedToken() ?? $this->fetchToken(),
            );
        } catch (LockTimeoutException $e) {
            $cached = $this->cachedToken();

            if ($cached !== null) {
                return $cached;
            }

            $this->logger->error('[NotificationClient] Timed out waiting for another process to refresh the access token.', [
                'waited' => self::LOCK_WAIT_SECONDS,
            ]);

            throw new AuthenticationException(
                'Timed out waiting for another process to refresh the access token.',
                previous: $e,
            );
        }
    }

    private function cachedToken(): ?Token
    {
        $cached = $this->readToken();

        if ($cached !== null && !$cached->isExpired()) {
            return $this->runtimeToken = $cached;
        }

        return null;
    }

    private function storeToken(Token $token, int $ttl): void
    {
        $this->cache->put(
            $this->cacheKey,
            $this->encrypter ? $this->encrypter->encrypt($token) : $token,
            $ttl,
        );
    }

    private function readToken(): ?Token
    {
        $value = $this->cache->get($this->cacheKey);

        if ($this->encrypter !== null) {
            if (!is_string($value)) {
                return null;
            }

            try {
                $value = $this->encrypter->decrypt($value);
            } catch (Throwable) {
                return null;
            }
        }

        return $value instanceof Token ? $value : null;
    }

    /**
     * A token response is only usable if it says how long it lives. Without that check a missing
     * `expires_in` casts to 0, every token is born expired, and the client refreshes on every single
     * call until the throttled token endpoint starts answering 429 — all of it silent.
     */
    private function parseTokenResponse(array $data): Token
    {
        if (empty($data['access_token']) || !is_string($data['access_token'])) {
            throw new AuthenticationException(sprintf(
                'Notification service returned a token response without a usable "access_token". Received keys: [%s]',
                implode(', ', array_keys($data)),
            ));
        }

        $lifetime = Token::remainingLifetime($data);

        if ($lifetime === null) {
            throw new AuthenticationException(sprintf(
                'Notification service returned a token response without a usable "expires_at" or numeric '
                . '"expires_in". Received keys: [%s]',
                implode(', ', array_keys($data)),
            ));
        }

        if ($lifetime <= 0) {
            throw new AuthenticationException(sprintf(
                'Notification service returned an access token that has already expired (%ds ago).',
                abs($lifetime),
            ));
        }

        return Token::fromResponse($data, $this->bufferSeconds);
    }

    /** Turns a silent refresh storm into one visible error line. */
    private function recordRefresh(): void
    {
        $now = time();
        $window = $now - self::REFRESH_STORM_WINDOW;

        $this->recentRefreshes = array_values(array_filter(
            [...$this->recentRefreshes, $now],
            fn(int $at): bool => $at > $window,
        ));

        if (count($this->recentRefreshes) < self::REFRESH_STORM_THRESHOLD) {
            return;
        }

        $this->logger->error('[NotificationClient] Access token was fetched repeatedly in a short window — the cached token is not being reused.', [
            'fetches' => count($this->recentRefreshes),
            'within'  => self::REFRESH_STORM_WINDOW,
            'check'   => 'token.cache_store must be shared across processes, and the service must report the token\'s remaining life',
        ]);

        $this->recentRefreshes = [];
    }

    private function fetchToken(): Token
    {
        try {
            $response = $this->httpClient->post($this->tokenEndpoint, [
                'json' => [
                    'client_id'     => $this->clientId,
                    'client_secret' => $this->clientSecret,
                ],
                'headers' => ['Accept' => 'application/json'],
            ]);

            $raw = (string) $response->getBody();
            $data = json_decode($raw, true);

            if (!is_array($data)) {
                throw new AuthenticationException(sprintf(
                    'Notification service returned a non-JSON token response (HTTP %d): %s',
                    $response->getStatusCode(),
                    mb_strimwidth($raw, 0, 200, '…'),
                ));
            }

            $token = $this->parseTokenResponse($data);

            $this->storeToken($token, max(1, $token->expiresAt - time()));
            $this->runtimeToken = $token;

            $this->recordRefresh();

            $this->logger->debug('[NotificationClient] Access token refreshed successfully.');

            return $token;
        } catch (AuthenticationException $e) {
            throw $e;
        } catch (ClientException $e) {
            if ($e->getResponse()->getStatusCode() === 429) {
                $retryAfter = (int) ($e->getResponse()->getHeaderLine('Retry-After') ?: 0) ?: null;

                $this->logger->warning('[NotificationClient] Token endpoint rate limit reached.', [
                    'retry_after' => $retryAfter,
                ]);

                throw new RateLimitException(
                    'Token endpoint rate limit reached. Make sure every worker and web process shares one '
                    . 'cache store for the access token, so the token is fetched once instead of per process.',
                    retryAfter: $retryAfter,
                    previous: $e,
                );
            }

            $this->logger->error('[NotificationClient] Failed to fetch access token.', [
                'status' => $e->getResponse()->getStatusCode(),
                'error'  => $e->getMessage(),
            ]);

            throw new AuthenticationException(
                'Could not authenticate with the notification service: ' . $e->getMessage(),
                previous: $e,
            );
        } catch (GuzzleException $e) {
            $this->logger->error('[NotificationClient] Failed to fetch access token.', [
                'error' => $e->getMessage(),
            ]);
            throw new AuthenticationException(
                'Could not authenticate with the notification service: ' . $e->getMessage(),
                previous: $e,
            );
        }
    }

    public function invalidate(): void
    {
        $this->runtimeToken = null;
        $this->cache->forget($this->cacheKey);
    }
}
