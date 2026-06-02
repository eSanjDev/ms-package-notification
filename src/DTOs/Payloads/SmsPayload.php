<?php

namespace Esanj\NotificationClient\DTOs\Payloads;

use Esanj\NotificationClient\Contracts\PayloadInterface;

final class SmsPayload implements PayloadInterface
{
    private function __construct(
        private readonly string $message,
    ) {}

    public static function fromMessage(string $message): self
    {
        return new self($message);
    }

    public function toArray(): array
    {
        return ['message' => $this->message];
    }
}