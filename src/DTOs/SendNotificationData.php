<?php

namespace Esanj\NotificationClient\DTOs;

use Esanj\NotificationClient\Contracts\PayloadInterface;

final class SendNotificationData
{
    /**
     * @param string          $recipient  Phone / email / device token.
     * @param PayloadInterface $payload   SmsPayload, EmailPayload, PushPayload, etc.
     * @param string|null     $channel    'sms' | 'email' | 'push'. Required when $providerId is null.
     * @param int|null        $providerId Specific provider ID. Overrides $channel-based selection.
     * @param string          $priority   'low' | 'medium' | 'high'.
     * @param string[]        $tags       Tag names to attach (must exist on the server).
     * @param array           $options    Extra options, e.g. ['lock_provider' => true].
     * @param string|null     $idempotencyKey Stable key that lets the service collapse a repeated send
     *                                        into the original one. Pass your own when the same logical
     *                                        send can be issued more than once (e.g. a retried job).
     */
    public function __construct(
        public readonly string $recipient,
        public readonly PayloadInterface $payload,
        public readonly ?string $channel = null,
        public readonly ?int $providerId = null,
        public readonly string $priority = 'medium',
        public readonly array $tags = [],
        public readonly array $options = [],
        public readonly ?string $idempotencyKey = null,
    ) {}

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