<?php

namespace Esanj\NotificationClient\Auth;

use InvalidArgumentException;

final class Token
{
    public function __construct(
        public readonly string $accessToken,
        public readonly string $tokenType,
        public readonly int $expiresAt,
    ) {}

    public static function fromResponse(array $response, int $bufferSeconds = 60): self
    {
        if (!isset($response['access_token'], $response['expires_in'])) {
            throw new InvalidArgumentException(
                'Token response must contain both "access_token" and "expires_in".'
            );
        }

        return new self(
            accessToken: (string) $response['access_token'],
            tokenType: (string) ($response['token_type'] ?? 'Bearer'),
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