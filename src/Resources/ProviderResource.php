<?php

namespace Esanj\NotificationClient\Resources;

use Carbon\CarbonImmutable;

final class ProviderResource
{
    public function __construct(
        public readonly int $id,
        public readonly string $providerName,
        public readonly string $providerChannel,
        public readonly int $providerId,
        public readonly int $orderColumn,
        public readonly CarbonImmutable $createdAt,
    ) {}

    public static function fromArray(array $item): self
    {
        return new self(
            id:              (int) $item['id'],
            providerName:    $item['provider_name'],
            providerChannel: $item['provider_channel'],
            providerId:      (int) $item['provider_id'],
            orderColumn:     (int) ($item['order_column'] ?? 0),
            createdAt:       CarbonImmutable::parse($item['created_at']),
        );
    }
}