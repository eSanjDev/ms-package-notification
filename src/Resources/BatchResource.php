<?php

namespace Esanj\NotificationClient\Resources;

use Carbon\CarbonImmutable;

final class BatchResource
{
    public function __construct(
        public readonly string $uuid,
        public readonly string $status,
        public readonly int $totalNotifications,
        public readonly int $processedNotifications,
        public readonly CarbonImmutable $createdAt,
        public readonly CarbonImmutable $updatedAt,
    ) {}

    public static function fromArray(array $response): self
    {
        $item = $response['data'] ?? $response;

        return new self(
            uuid:                   $item['uuid'],
            status:                 $item['status'],
            totalNotifications:     (int) ($item['total_notifications'] ?? 0),
            processedNotifications: (int) ($item['processed_notifications'] ?? 0),
            createdAt:              CarbonImmutable::parse($item['created_at']),
            updatedAt:              CarbonImmutable::parse($item['updated_at']),
        );
    }

    public function progressPercentage(): float
    {
        if ($this->totalNotifications === 0) {
            return 0.0;
        }

        return round(($this->processedNotifications / $this->totalNotifications) * 100, 2);
    }

    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }
}