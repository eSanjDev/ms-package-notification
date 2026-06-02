<?php

namespace Esanj\NotificationClient\Resources;

use Carbon\CarbonImmutable;

final class NotificationResource
{
    public function __construct(
        public readonly string $uuid,
        public readonly string $status,
        public readonly string $channel,
        public readonly string $recipient,
        public readonly ?string $batchUuid,
        public readonly ?CarbonImmutable $sentAt,
        public readonly CarbonImmutable $createdAt,
        public readonly CarbonImmutable $updatedAt,
    ) {}

    public static function fromArray(array $response): self
    {
        $item = $response['data'] ?? $response;

        return new self(
            uuid:      $item['uuid'],
            status:    $item['status'],
            channel:   $item['channel'],
            recipient: $item['recipient'],
            batchUuid: $item['batch_uuid'] ?? null,
            sentAt:    isset($item['sent_at']) ? CarbonImmutable::parse($item['sent_at']) : null,
            createdAt: CarbonImmutable::parse($item['created_at']),
            updatedAt: CarbonImmutable::parse($item['updated_at']),
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