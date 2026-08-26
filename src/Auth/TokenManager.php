<?php

namespace Esanj\NotificationClient\Auth;

use Esanj\NotificationClient\Contracts\TokenManagerInterface;
use Esanj\NotificationClient\Exceptions\AuthenticationException;
use Esanj\NotificationClient\Exceptions\RateLimitException;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Psr\Log\LoggerInterface;

class TokenManager implements TokenManagerInterface
{
    private ?Token $runtimeToken = null;

    public function __construct(
        private readonly Client $httpClient,
        private readonly CacheRepository $cache,
        private readonly LoggerInterface $logger,
        private readonly string $clientId,
        private readonly string $clientSecret,
        private readonly string $tokenEndpoint,
        private readonly string $cacheKey,
        private readonly int $bufferSeconds,
    ) {}

    public function getToken(): Token
    {
        if ($this->runtimeToken !== null && !$this->runtimeToken->isExpired()) {
            return $this->runtimeToken;
        }

        $cached = $this->cache->get($this->cacheKey);

        if ($cached instanceof Token && !$cached->isExpired()) {
            $this->runtimeToken = $cached;
            return $cached;
        }

        return $this->refresh();
    }

    public function refresh(): Token
    {
        try {
            $response = $this->httpClient->post($this->tokenEndpoint, [
                'json' => [
                    'client_id'     => $this->clientId,
                    'client_secret' => $this->clientSecret,
                ],
                'headers' => ['Accept' => 'application/json'],
            ]);

            $data = json_decode($response->getBody()->getContents(), true);

            if (empty($data['access_token'])) {
                throw new AuthenticationException('Notification service returned an invalid token response.');
            }

            $token = Token::fromResponse($data, $this->bufferSeconds);

            $ttl = max(1, (int) $data['expires_in'] - $this->bufferSeconds);
            $this->cache->put($this->cacheKey, $token, $ttl);
            $this->runtimeToken = $token;

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
