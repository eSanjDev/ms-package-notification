<?php

namespace Esanj\NotificationClient\Resources;

final class PaginatedResult implements \IteratorAggregate, \Countable
{
    /**
     * @param array $items      Hydrated resource objects.
     * @param int   $total      Total records across all pages.
     * @param int   $perPage    Records per page.
     * @param int   $currentPage Current page number.
     * @param int   $lastPage   Last available page.
     * @param int   $from       First record index on this page.
     * @param int   $to         Last record index on this page.
     */
    public function __construct(
        public readonly array $items,
        public readonly int $total,
        public readonly int $perPage,
        public readonly int $currentPage,
        public readonly int $lastPage,
        public readonly int $from,
        public readonly int $to,
    ) {}

    /**
     * @param callable $itemMapper  fn(array $item): mixed — maps a raw item array to a Resource object.
     */
    public static function fromArray(array $response, callable $itemMapper): self
    {
        $meta = $response['meta'] ?? [];

        return new self(
            items:       array_map($itemMapper, $response['data'] ?? []),
            total:       (int) ($meta['total'] ?? 0),
            perPage:     (int) ($meta['per_page'] ?? 15),
            currentPage: (int) ($meta['current_page'] ?? 1),
            lastPage:    (int) ($meta['last_page'] ?? 1),
            from:        (int) ($meta['from'] ?? 0),
            to:          (int) ($meta['to'] ?? 0),
        );
    }

    public function hasMorePages(): bool
    {
        return $this->currentPage < $this->lastPage;
    }

    public function nextPage(): ?int
    {
        return $this->hasMorePages() ? $this->currentPage + 1 : null;
    }

    public function isEmpty(): bool
    {
        return empty($this->items);
    }

    public function getIterator(): \Traversable
    {
        return new \ArrayIterator($this->items);
    }

    public function count(): int
    {
        return count($this->items);
    }
}
