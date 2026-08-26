<?php

namespace Esanj\NotificationClient\Resources;

use Carbon\CarbonImmutable;
use Esanj\NotificationClient\Resources\Concerns\HydratesSafely;

final class ProviderResource
{
    use HydratesSafely;

    public function __construct(
        public readonly int $id,
        public readonly ?string $providerName,
        public readonly ?string $providerChannel,
        public readonly int $providerId,
        public readonly int $orderColumn,
        public readonly CarbonImmutable $createdAt,
    ) {}

    public static function fromArray(array $item): self
    {
        return new self(
            id:              self::requiredInt($item, 'id'),
            providerName:    self::optionalString($item, 'provider_name'),
            providerChannel: self::optionalString($item, 'provider_channel'),
            providerId:      self::requiredInt($item, 'provider_id'),
            orderColumn:     (int) ($item['order_column'] ?? 0),
            createdAt:       self::requiredDate($item, 'created_at'),
        );
    }
}
