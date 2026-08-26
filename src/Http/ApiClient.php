<?php

namespace Esanj\NotificationClient\Http;

use Esanj\NotificationClient\Contracts\TokenManagerInterface;
use Esanj\NotificationClient\Exceptions\ApiException;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\ServerException;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;

class ApiClient
{
    private const TOKEN_INVALID_STATUS = 401;

    private const RATE_LIMITED_STATUS = 429;

    private const MAX_RETRY_AFTER_SECONDS = 30;

    private const SAFE_METHODS = ['GET', 'HEAD', 'OPTIONS'];

    public function __construct(
        private readonly Client                $httpClient,
        private readonly TokenManagerInterface $tokenManager,
        private readonly LoggerInterface       $logger,
        private readonly string                $baseUrl,
        private readonly int                   $retryAttempts,
        private readonly int                   $retrySleepMs,
        private readonly bool                  $idempotencySupported = false,
    )
    {
    }

    public function get(string $path, array $query = []): array
    {
        return $this->request('GET', $path, array_filter(['query' => $query ?: null]));
    }

    public function post(string $path, array $body = [], ?string $idempotencyKey = null): array
    {
        return $this->request('POST', $path, ['json' => $body], $idempotencyKey);
    }

    private function request(string $method, string $path, array $options = [], ?string $idempotencyKey = null): array
    {
        $url = rtrim($this->baseUrl, '/') . '/' . ltrim($path, '/');
        $lastException = null;

        $isSafe = in_array(strtoupper($method), self::SAFE_METHODS, true);

        if (!$isSafe && $idempotencyKey === null && $this->idempotencySupported) {
            $idempotencyKey = bin2hex(random_bytes(16));
        }

        $canRetry = $isSafe || ($idempotencyKey !== null && $this->idempotencySupported);

        $maxAttempts = max($this->retryAttempts, 1);
        $attemptsLeft = $canRetry ? $maxAttempts : 1;
        $tokenRefreshed = false;
        $rateLimitRetriesLeft = $maxAttempts - 1;
        $attempt = 0;

        while ($attemptsLeft > 0) {
            $attempt++;
            $attemptsLeft--;

            try {
                $token = $this->tokenManager->getToken();

                $headers = [
                    'Authorization' => $token->authorizationHeader(),
                    'Accept' => 'application/json',
                ];

                if ($idempotencyKey !== null) {
                    $headers['Idempotency-Key'] = $idempotencyKey;
                }

                $response = $this->httpClient->request($method, $url, array_merge($options, [
                    'headers' => $headers,
                ]));

                return json_decode($response->getBody()->getContents(), true) ?? [];

            } catch (ClientException $e) {
                $status = $e->getResponse()->getStatusCode();
                $body = json_decode($e->getResponse()->getBody()->getContents(), true) ?? [];

                $retryAfter = $status === self::RATE_LIMITED_STATUS
                    ? $this->retryAfterSeconds($e->getResponse())
                    : null;

                $apiException = new ApiException(
                    message: $body['message'] ?? ($status === self::RATE_LIMITED_STATUS
                        ? 'Rate limited by the notification service.'
                        : "HTTP {$status} error."),
                    statusCode: $status,
                    responseBody: $body,
                    previous: $e,
                    retryAfter: $retryAfter,
                );

                if ($status === self::RATE_LIMITED_STATUS && $rateLimitRetriesLeft > 0) {
                    $rateLimitRetriesLeft--;
                    $attemptsLeft = max($attemptsLeft, 1);

                    $waitSeconds = min($retryAfter, self::MAX_RETRY_AFTER_SECONDS);

                    $this->logger->warning('[NotificationClient] Rate limited, backing off.', [
                        'retry_after' => $retryAfter,
                        'waiting'     => $waitSeconds,
                        'attempt'     => $attempt,
                        'url'         => $url,
                    ]);

                    usleep($waitSeconds * 1_000_000);

                    $lastException = $apiException;
                    continue;
                }

                if ($status === self::TOKEN_INVALID_STATUS && !$tokenRefreshed) {
                    $tokenRefreshed = true;
                    $attemptsLeft = max($attemptsLeft, 1);

                    $this->logger->warning('[NotificationClient] Token rejected, refreshing and retrying.', [
                        'attempt' => $attempt,
                        'url' => $url,
                    ]);

                    $this->tokenManager->invalidate();
                    $this->tokenManager->refresh();

                    $lastException = $apiException;
                    continue;   // no sleep — this is not a network error
                }

                if ($status === self::RATE_LIMITED_STATUS) {
                    $this->logger->error('[NotificationClient] Still rate limited after backing off.', [
                        'retry_after' => $retryAfter,
                        'attempts'    => $attempt,
                        'url'         => $url,
                    ]);
                } elseif ($status === 403) {
                    $this->logger->error('[NotificationClient] Forbidden — this client lacks the service permission for this endpoint. Refreshing the token will not help.', [
                        'url' => $url,
                        'response' => $body,
                    ]);
                } elseif ($status === self::TOKEN_INVALID_STATUS) {
                    $this->logger->error('[NotificationClient] Authentication failed with a freshly refreshed token.', [
                        'url' => $url,
                        'response' => $body,
                    ]);
                }

                throw $apiException;

            } catch (ServerException $e) {
                $status = $e->getResponse()->getStatusCode();
                $body = json_decode($e->getResponse()->getBody()->getContents(), true) ?? [];

                $lastException = new ApiException(
                    message: $body['message'] ?? "HTTP {$status} server error.",
                    statusCode: $status,
                    responseBody: $body,
                    previous: $e,
                );

                if ($attemptsLeft <= 0) {
                    $this->logger->error('[NotificationClient] Server error, giving up.', [
                        'status' => $status,
                        'attempt' => $attempt,
                        'url' => $url,
                        'retryable' => $canRetry,
                    ]);
                    break;
                }

                $this->logger->warning('[NotificationClient] Server error, retrying.', [
                    'status' => $status,
                    'attempt' => $attempt,
                    'url' => $url,
                ]);

                $this->sleep();

            } catch (ConnectException $e) {
                $lastException = new ApiException(
                    message: 'Connection error: ' . $e->getMessage(),
                    statusCode: 0,
                    responseBody: [],
                    previous: $e,
                );

                if ($attemptsLeft <= 0) {
                    $this->logger->error('[NotificationClient] Connection error, giving up.', [
                        'attempt' => $attempt,
                        'url' => $url,
                        'error' => $e->getMessage(),
                        'retryable' => $canRetry,
                    ]);
                    break;
                }

                $this->logger->warning('[NotificationClient] Connection error, retrying.', [
                    'attempt' => $attempt,
                    'url' => $url,
                    'error' => $e->getMessage(),
                ]);

                $this->sleep();

            } catch (GuzzleException $e) {
                $this->logger->error('[NotificationClient] Unexpected HTTP error.', [
                    'attempt' => $attempt,
                    'url' => $url,
                    'error' => $e->getMessage(),
                ]);

                throw new ApiException(
                    message: 'Unexpected error: ' . $e->getMessage(),
                    statusCode: 0,
                    responseBody: [],
                    previous: $e,
                );
            }
        }

        $this->logger->error('[NotificationClient] Request failed.', [
            'url' => $url,
            'attempts' => $attempt,
        ]);

        throw $lastException ?? new ApiException('Request failed after all retry attempts.', 0, []);
    }

    private function retryAfterSeconds(ResponseInterface $response): int
    {
        $header = trim($response->getHeaderLine('Retry-After'));

        if ($header === '') {
            return 1;
        }

        if (is_numeric($header)) {
            return max(1, (int) $header);
        }

        $timestamp = strtotime($header);

        return $timestamp === false ? 1 : max(1, $timestamp - time());
    }

    private function sleep(): void
    {
        if ($this->retrySleepMs > 0) {
            usleep($this->retrySleepMs * 1_000);
        }
    }
}
