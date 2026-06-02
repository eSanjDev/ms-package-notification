<?php

namespace Esanj\NotificationClient\Contracts;

use Esanj\NotificationClient\DTOs\NotificationFilter;
use Esanj\NotificationClient\DTOs\SendBatchNotificationData;
use Esanj\NotificationClient\DTOs\SendNotificationData;
use Esanj\NotificationClient\Resources\BatchResource;
use Esanj\NotificationClient\Resources\NotificationResource;
use Esanj\NotificationClient\Resources\PaginatedResult;
use Esanj\NotificationClient\Resources\ProviderResource;
use Esanj\NotificationClient\Resources\TagResource;

interface NotificationClientInterface
{
    public function send(SendNotificationData $data): NotificationResource;

    public function sendBatch(SendBatchNotificationData $data): BatchResource;

    public function getNotification(string $uuid): NotificationResource;

    public function listNotifications(?NotificationFilter $filter = null): PaginatedResult;

    public function getBatch(string $uuid): BatchResource;

    public function listBatches(int $perPage = 15): PaginatedResult;

    /** @return ProviderResource[] */
    public function listProviders(): array;

    public function getProvider(int $id): ProviderResource;

    public function listTags(int $perPage = 15): PaginatedResult;

    public function getTag(int $id): TagResource;
}