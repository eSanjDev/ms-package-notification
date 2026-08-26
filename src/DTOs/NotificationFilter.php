<?php

namespace Esanj\NotificationClient\DTOs;

final class NotificationFilter
{
    /**
     * @param int          $perPage    Results per page (1–100).
     * @param string|null  $status     Filter by status: pending|queued|processing|sent|failed|delivered|undelivered.
     * @param string[]     $recipients Filter by specific recipients.
     * @param int          $page       Page number to fetch, starting at 1.
     */
    public function __construct(
        public readonly int $perPage = 15,
        public readonly ?string $status = null,
        public readonly array $recipients = [],
        public readonly int $page = 1,
    ) {}

    public function toArray(): array
    {
        return array_filter([
            'per_page'   => $this->perPage,
            'page'       => $this->page,
            'status'     => $this->status,
            'recipients' => $this->recipients ?: null,
        ], fn($v) => $v !== null);
    }

    public function withPage(int $page): self
    {
        return new self(
            perPage: $this->perPage,
            status: $this->status,
            recipients: $this->recipients,
            page: $page,
        );
    }

    public function nextPage(): self
    {
        return $this->withPage($this->page + 1);
    }
}
