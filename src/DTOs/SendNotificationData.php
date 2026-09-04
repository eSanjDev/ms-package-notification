<?php

namespace Esanj\NotificationClient\DTOs;

use Esanj\NotificationClient\Contracts\PayloadInterface;
use Esanj\NotificationClient\DTOs\Concerns\RejectsProviderWithPattern;
use Esanj\NotificationClient\Enums\NotificationChannel;
use Esanj\NotificationClient\Enums\NotificationPriority;

final class SendNotificationData
{
    use RejectsProviderWithPattern;

    public readonly ?string $channel;
    public readonly ?string $priority;

    public function __construct(
        public readonly string $recipient,
        public readonly PayloadInterface $payload,
        NotificationChannel|string|null $channel = null,
        public readonly ?int $providerId = null,
        NotificationPriority|string|null $priority = null,
        public readonly array $tags = [],
        public readonly array $options = [],
        public readonly ?string $idempotencyKey = null,
    ) {
        $this->channel = NotificationChannel::normalize($channel, 'channel');
        $this->priority = NotificationPriority::normalize($priority, 'priority');

        $this->assertProviderAllowed($payload, $providerId);
    }

    public function toArray(): array
    {
        return array_filter([
            'channel'     => $this->channel,
            'provider_id' => $this->providerId,
            'recipient'   => $this->recipient,
            'priority'    => $this->priority,
            'tags'        => $this->tags ?: null,
            'options'     => $this->options ?: null,
            'payload'     => $this->payload->toArray(),
        ], fn($v) => $v !== null);
    }
}
