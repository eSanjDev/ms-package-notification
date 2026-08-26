<?php

namespace Esanj\NotificationClient\Resources\Concerns;

use Carbon\CarbonImmutable;
use Esanj\NotificationClient\Exceptions\UnexpectedResponseException;
use Throwable;

trait HydratesSafely
{
    private static function required(array $item, string $key): mixed
    {
        if (!array_key_exists($key, $item) || $item[$key] === null) {
            throw new UnexpectedResponseException(sprintf(
                'Notification service response is missing required field "%s". Received keys: [%s]',
                $key,
                implode(', ', array_keys($item)),
            ));
        }

        return $item[$key];
    }

    private static function requiredString(array $item, string $key): string
    {
        return (string) self::required($item, $key);
    }

    private static function requiredInt(array $item, string $key): int
    {
        return (int) self::required($item, $key);
    }

    private static function optionalString(array $item, string $key): ?string
    {
        $value = $item[$key] ?? null;

        return $value === null ? null : (string) $value;
    }

    private static function requiredDate(array $item, string $key): CarbonImmutable
    {
        return self::parseDate($key, self::required($item, $key));
    }

    private static function optionalDate(array $item, string $key): ?CarbonImmutable
    {
        $value = $item[$key] ?? null;

        return $value === null ? null : self::parseDate($key, $value);
    }

    private static function parseDate(string $key, mixed $value): CarbonImmutable
    {
        try {
            return CarbonImmutable::parse($value);
        } catch (Throwable $e) {
            throw new UnexpectedResponseException(
                sprintf('Field "%s" is not a valid date: %s', $key, var_export($value, true)),
                previous: $e,
            );
        }
    }
}
