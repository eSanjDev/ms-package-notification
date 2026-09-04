<?php

namespace Esanj\NotificationClient\Exceptions;

use Throwable;

class ApiException extends NotificationClientException
{
    public function __construct(
        string $message,
        public readonly int $statusCode,
        public readonly array $responseBody,
        ?Throwable $previous = null,
        public readonly ?int $retryAfter = null,
    ) {
        parent::__construct($message, $statusCode, $previous);
    }

    public function getErrors(): array
    {
        return $this->responseBody['errors'] ?? [];
    }

    public function isBadRequest(): bool
    {
        return $this->statusCode === 400;
    }

    public function isUnauthorized(): bool
    {
        return $this->statusCode === 401;
    }

    public function isForbidden(): bool
    {
        return $this->statusCode === 403;
    }

    public function isNotFound(): bool
    {
        return $this->statusCode === 404;
    }

    public function isValidationError(): bool
    {
        return $this->statusCode === 422;
    }

    public function isRateLimited(): bool
    {
        return $this->statusCode === 429;
    }

    public function isServerError(): bool
    {
        return $this->statusCode >= 500;
    }

    public function isConnectionError(): bool
    {
        return $this->statusCode === 0;
    }

    public function isClientInputError(): bool
    {
        return $this->isBadRequest() || $this->isValidationError();
    }
}
