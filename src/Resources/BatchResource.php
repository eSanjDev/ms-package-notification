<?php

namespace Esanj\NotificationClient\Resources;

use Carbon\CarbonImmutable;
use Esanj\NotificationClient\Enums\BatchStatus;
use Esanj\NotificationClient\Resources\Concerns\HydratesSafely;

final class BatchResource
{
    use HydratesSafely;

    public function __construct(
        public readonly string $uuid,
        public readonly string $status,
        public readonly int $totalNotifications,
        public readonly int $processedNotifications,
        public readonly CarbonImmutable $createdAt,
        public readonly ?CarbonImmutable $updatedAt,
    ) {}

    public static function fromArray(array $item): self
    {
        return new self(
            uuid:                   self::requiredString($item, 'uuid'),
            status:                 self::requiredString($item, 'status'),
            totalNotifications:     (int) ($item['total_notifications'] ?? 0),
            processedNotifications: (int) ($item['processed_notifications'] ?? 0),
            createdAt:              self::requiredDate($item, 'created_at'),
            updatedAt:              self::optionalDate($item, 'updated_at'),
        );
    }

    public function progressPercentage(): float
    {
        if ($this->totalNotifications === 0) {
            return 0.0;
        }

        return round(($this->processedNotifications / $this->totalNotifications) * 100, 2);
    }

    public function isPending(): bool
    {
        return $this->status === BatchStatus::PENDING->value;
    }

    public function isProcessing(): bool
    {
        return $this->status === BatchStatus::PROCESSING->value;
    }

    public function isCanceled(): bool
    {
        return $this->status === BatchStatus::CANCELED->value;
    }

    public function isCompleted(): bool
    {
        return $this->status === BatchStatus::COMPLETED->value;
    }
}
