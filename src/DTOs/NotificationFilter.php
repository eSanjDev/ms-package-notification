<?php

namespace Esanj\NotificationClient\DTOs;

use Esanj\NotificationClient\Enums\NotificationStatus;
use Esanj\NotificationClient\Exceptions\InvalidInputException;

final class NotificationFilter
{
    private const MAX_PER_PAGE = 100;

    private const MAX_RECIPIENTS = 100;

    public readonly int $perPage;
    public readonly int $page;
    public readonly ?string $status;


    public function __construct(
        int                            $perPage = 15,
        NotificationStatus|string|null $status = null,
        public readonly array          $recipients = [],
        int                            $page = 1,
    )
    {
        if (count($recipients) > self::MAX_RECIPIENTS) {
            throw new InvalidInputException(sprintf(
                'The notification service filters on at most %d recipients at a time; %d given.',
                self::MAX_RECIPIENTS,
                count($recipients),
            ));
        }

        $this->perPage = min(max($perPage, 1), self::MAX_PER_PAGE);
        $this->page = max($page, 1);
        $this->status = NotificationStatus::normalize($status, 'status');
    }

    public function toArray(): array
    {
        return array_filter([
            'per_page' => $this->perPage,
            'page' => $this->page,
            'status' => $this->status,
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
