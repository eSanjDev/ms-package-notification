<?php

namespace Esanj\NotificationClient\DTOs;

final class NotificationFilter
{
    /**
     * @param int          $perPage    Results per page (1–100).
     * @param string|null  $status     Filter by status: pending|queued|processing|sent|failed|delivered|undelivered.
     * @param string[]     $recipients Filter by specific recipients.
     */
    public function __construct(
        public readonly int $perPage = 15,
        public readonly ?string $status = null,
        public readonly array $recipients = [],
    ) {}

    public function toArray(): array
    {
        return array_filter([
            'per_page'   => $this->perPage,
            'status'     => $this->status,
            'recipients' => $this->recipients ?: null,
        ], fn($v) => $v !== null);
    }
}