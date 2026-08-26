<?php

namespace Esanj\NotificationClient\DTOs;

use Esanj\NotificationClient\Contracts\PayloadInterface;

final class SendBatchNotificationData
{
    /**
     * @param string[]         $recipients  List of recipients (max 5000).
     * @param PayloadInterface $payload     SmsPayload, EmailPayload, PushPayload, etc.
     * @param string|null      $channel     'sms' | 'email' | 'push'. Required when $providerId is null.
     * @param int|null         $providerId  Specific provider ID.
     * @param string           $priority    'low' | 'medium' | 'high'.
     * @param string[]         $tags        Tag names to attach.
     * @param string|null      $batchName   Optional label for the batch.
     * @param array            $options     Extra options.
     * @param string|null      $idempotencyKey Stable key that lets the service collapse a repeated batch
     *                                         into the original one.
     */
    public function __construct(
        public readonly array $recipients,
        public readonly PayloadInterface $payload,
        public readonly ?string $channel = null,
        public readonly ?int $providerId = null,
        public readonly string $priority = 'low',
        public readonly array $tags = [],
        public readonly ?string $batchName = null,
        public readonly array $options = [],
        public readonly ?string $idempotencyKey = null,
    ) {}

    public function toArray(): array
    {
        return array_filter([
            'channel'     => $this->channel,
            'provider_id' => $this->providerId,
            'recipients'  => $this->recipients,
            'priority'    => $this->priority,
            'tags'        => $this->tags ?: null,
            'batch_name'  => $this->batchName,
            'options'     => $this->options ?: null,
            'payload'     => $this->payload->toArray(),
        ], fn($v) => $v !== null);
    }
}