<?php

namespace TopoclimbCH\Core\Pagination;

/**
 * Simple paginator for debugging purposes
 */
class SimplePaginator
{
    private array $items;
    private int $currentPage;
    private int $perPage;
    private int $total;

    public function __construct(array $items, int $currentPage, int $perPage, int $total)
    {
        $this->items = $items;
        $this->currentPage = $currentPage;
        $this->perPage = $perPage;
        $this->total = $total;
    }

    public function getItems(): array
    {
        return $this->items;
    }

    public function getCurrentPage(): int
    {
        return $this->currentPage;
    }

    public function getPerPage(): int
    {
        return $this->perPage;
    }

    public function getTotal(): int
    {
        return $this->total;
    }

    public function getTotalPages(): int
    {
        return max(1, (int) ceil($this->total / $this->perPage));
    }

    public function hasNextPage(): bool
    {
        return $this->currentPage < $this->getTotalPages();
    }

    public function hasPreviousPage(): bool
    {
        return $this->currentPage > 1;
    }

    public function getData(): array
    {
        return $this->items;
    }

    public function getTotalItems(): int
    {
        return $this->total;
    }
}