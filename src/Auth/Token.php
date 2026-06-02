<?php

namespace Esanj\NotificationClient\Auth;

final class Token
{
    public function __construct(
        public readonly string $accessToken,
        public readonly string $tokenType,
        public readonly int $expiresAt,
    ) {}

    public static function fromResponse(array $response, int $bufferSeconds = 60): self
    {
        return new self(
            accessToken: $response['access_token'],
            tokenType: $response['token_type'] ?? 'Bearer',
            expiresAt: time() + (int) $response['expires_in'] - $bufferSeconds,
        );
    }

    public function isExpired(): bool
    {
        return time() >= $this->expiresAt;
    }

    public function authorizationHeader(): string
    {
        return "{$this->tokenType} {$this->accessToken}";
    }
}