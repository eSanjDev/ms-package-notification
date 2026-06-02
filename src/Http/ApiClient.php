<?php

namespace Esanj\NotificationClient\Http;

use Esanj\NotificationClient\Contracts\TokenManagerInterface;
use Esanj\NotificationClient\Exceptions\ApiException;
use Esanj\NotificationClient\Exceptions\AuthenticationException;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\ServerException;
use Psr\Log\LoggerInterface;

class ApiClient
{
    private const TOKEN_INVALID_STATUSES = [401, 403];

    public function __construct(
        private readonly Client $httpClient,
        private readonly TokenManagerInterface $tokenManager,
        private readonly LoggerInterface $logger,
        private readonly string $baseUrl,
        private readonly int $retryAttempts,
        private readonly int $retrySleepMs,
    ) {}

    public function get(string $path, array $query = []): array
    {
        return $this->request('GET', $path, array_filter(['query' => $query ?: null]));
    }

    public function post(string $path, array $body = []): array
    {
        return $this->request('POST', $path, ['json' => $body]);
    }

    private function request(string $method, string $path, array $options = []): array
    {
        $url = rtrim($this->baseUrl, '/') . '/' . ltrim($path, '/');
        $lastException = null;

        for ($attempt = 1; $attempt <= $this->retryAttempts; $attempt++) {
            try {
                $token = $this->tokenManager->getToken();

                $response = $this->httpClient->request($method, $url, array_merge($options, [
                    'headers' => [
                        'Authorization' => $token->authorizationHeader(),
                        'Accept'        => 'application/json',
                    ],
                ]));

                return json_decode($response->getBody()->getContents(), true) ?? [];

            } catch (ClientException $e) {
                $status = $e->getResponse()->getStatusCode();
                $body   = json_decode($e->getResponse()->getBody()->getContents(), true) ?? [];

                // Validation or not-found errors are not retryable
                if (!in_array($status, self::TOKEN_INVALID_STATUSES, true)) {
                    throw new ApiException(
                        message: $body['message'] ?? "HTTP {$status} error.",
                        statusCode: $status,
                        responseBody: $body,
                        previous: $e,
                    );
                }

                // Token rejected — try to refresh on subsequent attempts
                if ($attempt < $this->retryAttempts) {
                    $this->logger->warning('[NotificationClient] Token rejected, refreshing and retrying.', [
                        'status'  => $status,
                        'attempt' => $attempt,
                        'url'     => $url,
                    ]);

                    $this->tokenManager->invalidate();
                    $this->tokenManager->refresh();
                    $this->sleep();

                    $lastException = new ApiException(
                        message: $body['message'] ?? "HTTP {$status} error.",
                        statusCode: $status,
                        responseBody: $body,
                        previous: $e,
                    );
                    continue;
                }

                // Final attempt with token error
                $this->logger->error('[NotificationClient] Authentication failed after all retry attempts.', [
                    'status'   => $status,
                    'url'      => $url,
                    'response' => $body,
                ]);

                throw new ApiException(
                    message: $body['message'] ?? "HTTP {$status} error.",
                    statusCode: $status,
                    responseBody: $body,
                    previous: $e,
                );

            } catch (ServerException $e) {
                $status = $e->getResponse()->getStatusCode();
                $body   = json_decode($e->getResponse()->getBody()->getContents(), true) ?? [];

                $this->logger->warning('[NotificationClient] Server error, retrying.', [
                    'status'  => $status,
                    'attempt' => $attempt,
                    'url'     => $url,
                ]);

                $lastException = new ApiException(
                    message: $body['message'] ?? "HTTP {$status} server error.",
                    statusCode: $status,
                    responseBody: $body,
                    previous: $e,
                );

                if ($attempt < $this->retryAttempts) {
                    $this->sleep();
                }

            } catch (ConnectException $e) {
                $this->logger->warning('[NotificationClient] Connection error, retrying.', [
                    'attempt' => $attempt,
                    'url'     => $url,
                    'error'   => $e->getMessage(),
                ]);

                $lastException = new ApiException(
                    message: 'Connection error: ' . $e->getMessage(),
                    statusCode: 0,
                    responseBody: [],
                    previous: $e,
                );

                if ($attempt < $this->retryAttempts) {
                    $this->sleep();
                }

            } catch (GuzzleException $e) {
                $this->logger->error('[NotificationClient] Unexpected HTTP error.', [
                    'attempt' => $attempt,
                    'url'     => $url,
                    'error'   => $e->getMessage(),
                ]);

                throw new ApiException(
                    message: 'Unexpected error: ' . $e->getMessage(),
                    statusCode: 0,
                    responseBody: [],
                    previous: $e,
                );
            }
        }

        $this->logger->error('[NotificationClient] All retry attempts exhausted.', [
            'url'      => $url,
            'attempts' => $this->retryAttempts,
        ]);

        throw $lastException ?? new ApiException('Request failed after all retry attempts.', 0, []);
    }

    private function sleep(): void
    {
        if ($this->retrySleepMs > 0) {
            usleep($this->retrySleepMs * 1_000);
        }
    }
}