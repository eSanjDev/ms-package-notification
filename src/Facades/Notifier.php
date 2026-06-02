<?php

namespace Esanj\NotificationClient\Facades;

use Esanj\NotificationClient\Contracts\NotificationClientInterface;
use Esanj\NotificationClient\DTOs\NotificationFilter;
use Esanj\NotificationClient\DTOs\SendBatchNotificationData;
use Esanj\NotificationClient\DTOs\SendNotificationData;
use Esanj\NotificationClient\Resources\BatchResource;
use Esanj\NotificationClient\Resources\NotificationResource;
use Esanj\NotificationClient\Resources\PaginatedResult;
use Esanj\NotificationClient\Resources\ProviderResource;
use Esanj\NotificationClient\Resources\TagResource;
use Illuminate\Support\Facades\Facade;

/**
 * @method static NotificationResource send(SendNotificationData $data)
 * @method static BatchResource sendBatch(SendBatchNotificationData $data)
 * @method static NotificationResource getNotification(string $uuid)
 * @method static PaginatedResult listNotifications(?NotificationFilter $filter = null)
 * @method static BatchResource getBatch(string $uuid)
 * @method static PaginatedResult listBatches(int $perPage = 15)
 * @method static ProviderResource[] listProviders()
 * @method static ProviderResource getProvider(int $id)
 * @method static PaginatedResult listTags(int $perPage = 15)
 * @method static TagResource getTag(int $id)
 *
 * @see \Esanj\NotificationClient\NotificationClient
 */
class Notifier extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return NotificationClientInterface::class;
    }
}