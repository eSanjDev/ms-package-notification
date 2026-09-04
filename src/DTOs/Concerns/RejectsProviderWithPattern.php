<?php

namespace Esanj\NotificationClient\DTOs\Concerns;

use Esanj\NotificationClient\Contracts\PayloadInterface;
use Esanj\NotificationClient\Exceptions\InvalidInputException;

trait RejectsProviderWithPattern
{
    private function assertProviderAllowed(PayloadInterface $payload, ?int $providerId): void
    {
        if ($providerId !== null && array_key_exists('pattern', $payload->toArray())) {
            throw new InvalidInputException(
                'providerId cannot be combined with a pattern payload: the pattern already determines '
                . 'which provider sends it, and the notification service rejects the pair.'
            );
        }
    }
}
