<?php

namespace Esanj\NotificationClient\Auth;

use Esanj\NotificationClient\Exceptions\InvalidInputException;

final class Token
{
    public function __construct(
        public readonly string $accessToken,
        public readonly string $tokenType,
        public readonly int $expiresAt,
    ) {}

    public static function fromResponse(array $response, int $bufferSeconds = 60): self
    {
        $lifetime = self::remainingLifetime($response);

        if (empty($response['access_token']) || $lifetime === null) {
            throw new InvalidInputException(
                'Token response must contain "access_token" and either "expires_at" or "expires_in".'
            );
        }

        return new self(
            accessToken: (string) $response['access_token'],
            tokenType: (string) ($response['token_type'] ?? 'Bearer'),
            expiresAt: time() + max(1, $lifetime - $bufferSeconds),
        );
    }

    public static function remainingLifetime(array $response): ?int
    {
        $expiresAt = $response['expires_at'] ?? null;

        if (is_string($expiresAt) && ($timestamp = strtotime($expiresAt)) !== false) {
            return $timestamp - time();
        }

        return is_numeric($response['expires_in'] ?? null)
            ? (int) $response['expires_in']
            : null;
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
