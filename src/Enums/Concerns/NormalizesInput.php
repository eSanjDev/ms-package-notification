<?php

namespace Esanj\NotificationClient\Enums\Concerns;

use Esanj\NotificationClient\Exceptions\InvalidInputException;

trait NormalizesInput
{
    public static function normalize(self|string|null $value, string $field): ?string
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof self) {
            return $value->value;
        }

        $case = self::tryFrom($value);

        if ($case === null) {
            throw new InvalidInputException(sprintf(
                'Unknown %s "%s". The notification service accepts: %s.',
                $field,
                $value,
                implode(', ', array_column(self::cases(), 'value')),
            ));
        }

        return $case->value;
    }
}
