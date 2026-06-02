<?php

namespace Esanj\NotificationClient\DTOs\Payloads;

use Esanj\NotificationClient\Contracts\PayloadInterface;

final class SmsPatternPayload implements PayloadInterface
{
    private function __construct(
        private readonly string $key,
        private readonly array $tokens,
    ) {}

    /**
     * @param array<string, string> $tokens Key-value pairs matching the pattern variables.
     */
    public static function make(string $key, array $tokens): self
    {
        return new self($key, $tokens);
    }

    public function toArray(): array
    {
        return [
            'pattern' => [
                'key'    => $this->key,
                'tokens' => $this->tokens,
            ],
        ];
    }
}