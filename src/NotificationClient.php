<?php

namespace Esanj\NotificationClient;

use Esanj\NotificationClient\Contracts\NotificationClientInterface;
use Esanj\NotificationClient\DTOs\NotificationFilter;
use Esanj\NotificationClient\DTOs\SendBatchNotificationData;
use Esanj\NotificationClient\DTOs\SendNotificationData;
use Esanj\NotificationClient\Http\ApiClient;
use Esanj\NotificationClient\Resources\BatchResource;
use Esanj\NotificationClient\Resources\NotificationResource;
use Esanj\NotificationClient\Resources\PaginatedResult;
use Esanj\NotificationClient\Resources\ProviderResource;
use Esanj\NotificationClient\Resources\TagResource;
use Generator;

class NotificationClient implements NotificationClientInterface
{
    private const MAX_PER_PAGE = 100;

    public function __construct(private readonly ApiClient $apiClient) {}

    public function send(SendNotificationData $data): NotificationResource
    {
        $response = $this->apiClient->post('api/v1/send', $data->toArray(), $data->idempotencyKey);
        return NotificationResource::fromArray($response);
    }

    public function sendBatch(SendBatchNotificationData $data): BatchResource
    {
        $response = $this->apiClient->post('api/v1/send-batch', $data->toArray(), $data->idempotencyKey);
        return BatchResource::fromArray($response);
    }

    public function getNotification(string $uuid): NotificationResource
    {
        $response = $this->apiClient->get("api/v1/notifications/{$uuid}");
        return NotificationResource::fromArray($response);
    }

    public function listNotifications(?NotificationFilter $filter = null): PaginatedResult
    {
        $response = $this->apiClient->get('api/v1/notifications', ($filter ?? new NotificationFilter())->toArray());
        return PaginatedResult::fromArray($response, NotificationResource::fromArray(...));
    }

    public function eachNotification(?NotificationFilter $filter = null): Generator
    {
        $filter = $filter ?? new NotificationFilter();

        while (true) {
            $result = $this->listNotifications($filter);

            foreach ($result->items as $notification) {
                yield $notification;
            }

            if (!$result->hasMorePages() || $result->currentPage !== $filter->page) {
                return;
            }

            $filter = $filter->nextPage();
        }
    }

    public function getBatch(string $uuid): BatchResource
    {
        $response = $this->apiClient->get("api/v1/notification-batches/{$uuid}");
        return BatchResource::fromArray($response);
    }

    public function listBatches(int $perPage = 15, int $page = 1): PaginatedResult
    {
        $response = $this->apiClient->get('api/v1/notification-batches', [
            'per_page' => $perPage,
            'page'     => $page,
        ]);
        return PaginatedResult::fromArray($response, BatchResource::fromArray(...));
    }

    public function listProviders(): array
    {
        $providers = [];
        $page = 1;

        while (true) {
            $result = $this->listProvidersPage(page: $page);
            $providers = array_merge($providers, $result->items);

            if (!$result->hasMorePages() || $result->currentPage !== $page) {
                return $providers;
            }

            $page++;
        }
    }

    public function listProvidersPage(int $perPage = self::MAX_PER_PAGE, int $page = 1): PaginatedResult
    {
        $response = $this->apiClient->get('api/v1/client-providers', [
            'per_page' => min(max($perPage, 1), self::MAX_PER_PAGE),
            'page'     => $page,
        ]);

        return PaginatedResult::fromArray($response, ProviderResource::fromArray(...));
    }

    public function getProvider(int $id): ProviderResource
    {
        $response = $this->apiClient->get("api/v1/client-providers/{$id}");
        return ProviderResource::fromArray($response['data'] ?? $response);
    }

    public function listTags(int $perPage = 15, int $page = 1): PaginatedResult
    {
        $response = $this->apiClient->get('api/v1/tags', [
            'per_page' => $perPage,
            'page'     => $page,
        ]);
        return PaginatedResult::fromArray($response, TagResource::fromArray(...));
    }

    public function getTag(int $id): TagResource
    {
        $response = $this->apiClient->get("api/v1/tags/{$id}");
        return TagResource::fromArray($response['data'] ?? $response);
    }
}
