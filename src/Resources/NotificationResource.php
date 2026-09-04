<?php

namespace Esanj\NotificationClient\Resources;

use Carbon\CarbonImmutable;
use Esanj\NotificationClient\Enums\NotificationStatus;
use Esanj\NotificationClient\Resources\Concerns\HydratesSafely;

final class NotificationResource
{
    use HydratesSafely;

    public function __construct(
        public readonly string $uuid,
        public readonly string $status,
        public readonly string $channel,
        public readonly string $recipient,
        public readonly ?string $batchUuid,
        public readonly ?CarbonImmutable $sentAt,
        public readonly CarbonImmutable $createdAt,
        public readonly ?CarbonImmutable $updatedAt,
    ) {}

    public static function fromArray(array $item): self
    {
        return new self(
            uuid:      self::requiredString($item, 'uuid'),
            status:    self::requiredString($item, 'status'),
            channel:   self::requiredString($item, 'channel'),
            recipient: self::requiredString($item, 'recipient'),
            batchUuid: self::optionalString($item, 'batch_uuid'),
            sentAt:    self::optionalDate($item, 'sent_at'),
            createdAt: self::requiredDate($item, 'created_at'),
            updatedAt: self::optionalDate($item, 'updated_at'),
        );
    }

    public function isSent(): bool
    {
        return $this->status === NotificationStatus::SENT->value;
    }

    public function isDelivered(): bool
    {
        return $this->status === NotificationStatus::DELIVERED->value;
    }

    public function isFailed(): bool
    {
        return in_array($this->status, [
            NotificationStatus::FAILED->value,
            NotificationStatus::UNDELIVERED->value,
        ], true);
    }

    public function isPending(): bool
    {
        return in_array($this->status, [
            NotificationStatus::PENDING->value,
            NotificationStatus::QUEUED->value,
            NotificationStatus::PROCESSING->value,
        ], true);
    }
}
