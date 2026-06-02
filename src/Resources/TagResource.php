<?php

namespace Esanj\NotificationClient\Resources;

use Carbon\CarbonImmutable;

final class TagResource
{
    public function __construct(
        public readonly int $id,
        public readonly string $name,
        public readonly ?string $description,
        public readonly ?string $color,
        public readonly int $usedCount,
        public readonly CarbonImmutable $createdAt,
        public readonly CarbonImmutable $updatedAt,
    ) {}

    public static function fromArray(array $item): self
    {
        return new self(
            id:          (int) $item['id'],
            name:        $item['name'],
            description: $item['description'] ?? null,
            color:       $item['color'] ?? null,
            usedCount:   (int) ($item['used_count'] ?? 0),
            createdAt:   CarbonImmutable::parse($item['created_at']),
            updatedAt:   CarbonImmutable::parse($item['updated_at']),
        );
    }
}