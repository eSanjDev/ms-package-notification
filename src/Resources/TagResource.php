<?php

namespace Esanj\NotificationClient\Resources;

use Carbon\CarbonImmutable;
use Esanj\NotificationClient\Resources\Concerns\HydratesSafely;

final class TagResource
{
    use HydratesSafely;

    public function __construct(
        public readonly int $id,
        public readonly string $name,
        public readonly ?string $description,
        public readonly ?string $color,
        public readonly int $usedCount,
        public readonly CarbonImmutable $createdAt,
        public readonly ?CarbonImmutable $updatedAt,
    ) {}

    public static function fromArray(array $item): self
    {
        return new self(
            id:          self::requiredInt($item, 'id'),
            name:        self::requiredString($item, 'name'),
            description: self::optionalString($item, 'description'),
            color:       self::optionalString($item, 'color'),
            usedCount:   (int) ($item['used_count'] ?? 0),
            createdAt:   self::requiredDate($item, 'created_at'),
            updatedAt:   self::optionalDate($item, 'updated_at'),
        );
    }
}
