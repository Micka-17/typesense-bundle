<?php

declare(strict_types=1);

namespace Micka17\TypesenseBundle\Dto;

class Paginator
{
    /**
     * @param array<int, array<string, mixed>> $items
     */
    public function __construct(
        public readonly array $items,
        public readonly int $currentPage,
        public readonly int $perPage,
        public readonly int $total,
    ) {}

    public int $lastPage {
        get => max(1, (int) ceil($this->total / $this->perPage));
    }

    public bool $hasPreviousPage {
        get => $this->currentPage > 1;
    }

    public ?int $previousPage {
        get => $this->hasPreviousPage ? $this->currentPage - 1 : null;
    }

    public bool $hasNextPage {
        get => $this->currentPage < $this->lastPage;
    }

    public ?int $nextPage {
        get => $this->hasNextPage ? $this->currentPage + 1 : null;
    }
}
