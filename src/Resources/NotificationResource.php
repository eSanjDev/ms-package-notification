<?php

namespace Esanj\NotificationClient\Resources;

use Carbon\CarbonImmutable;
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

    public static function fromArray(array $response): self
    {
        $item = $response['data'] ?? $response;

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
        return $this->status === 'sent';
    }

    public function isFailed(): bool
    {
        return $this->status === 'failed';
    }

    public function isPending(): bool
    {
        return in_array($this->status, ['pending', 'queued', 'processing'], true);
    }
}
