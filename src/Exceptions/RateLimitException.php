<?php

namespace Esanj\NotificationClient\Exceptions;

use Throwable;

class RateLimitException extends NotificationClientException
{
    public function __construct(
        string               $message,
        public readonly ?int $retryAfter = null,
        ?Throwable           $previous = null,
    )
    {
        parent::__construct($message, 429, $previous);
    }
}
