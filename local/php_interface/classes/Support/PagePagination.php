<?php

declare(strict_types=1);

namespace Project\Support;

use InvalidArgumentException;

/**
 * Рассчитывает состояние постраничной навигации без чтения запроса и формирования HTML.
 */
final class PagePagination
{
    private readonly int $totalPages;
    private readonly int $currentPage;

    public function __construct(
        private readonly int $totalItems,
        private readonly int $perPage = 20,
        int $currentPage = 1
    ) {
        if ($totalItems < 0) {
            throw new InvalidArgumentException('The total item count cannot be negative.');
        }

        if ($perPage < 1) {
            throw new InvalidArgumentException('The page size must be greater than zero.');
        }

        $this->totalPages = (int) ceil($totalItems / $perPage);
        $this->currentPage = $this->totalPages === 0
            ? 1
            : max(1, min($currentPage, $this->totalPages));
    }

    public function getTotalItems(): int
    {
        return $this->totalItems;
    }

    public function getPerPage(): int
    {
        return $this->perPage;
    }

    public function getTotalPages(): int
    {
        return $this->totalPages;
    }

    public function getCurrentPage(): int
    {
        return $this->currentPage;
    }

    public function getOffset(): int
    {
        return ($this->currentPage - 1) * $this->perPage;
    }

    public function getLimit(): int
    {
        return $this->perPage;
    }

    public function hasPreviousPage(): bool
    {
        return $this->totalPages > 0 && $this->currentPage > 1;
    }

    public function hasNextPage(): bool
    {
        return $this->currentPage < $this->totalPages;
    }

    public function getPreviousPage(): ?int
    {
        return $this->hasPreviousPage() ? $this->currentPage - 1 : null;
    }

    public function getNextPage(): ?int
    {
        return $this->hasNextPage() ? $this->currentPage + 1 : null;
    }

    /**
     * Возвращает номера страниц с маркерами «…» для пропущенных диапазонов.
     *
     * Если страницы существуют, первая и последняя всегда включаются в результат.
     *
     * @return list<int|string>
     */
    public function getPageItems(int $sidePages = 2): array
    {
        if ($sidePages < 0) {
            throw new InvalidArgumentException('The number of side pages cannot be negative.');
        }

        if ($this->totalPages === 0) {
            return [];
        }

        if ($this->totalPages <= ($sidePages * 2) + 5) {
            return range(1, $this->totalPages);
        }

        $start = max(2, $this->currentPage - $sidePages);
        $end = min($this->totalPages - 1, $this->currentPage + $sidePages);

        $items = [1];

        if ($start > 2) {
            $items[] = '…';
        }

        for ($page = $start; $page <= $end; $page++) {
            $items[] = $page;
        }

        if ($end < $this->totalPages - 1) {
            $items[] = '…';
        }

        $items[] = $this->totalPages;

        return $items;
    }
}
