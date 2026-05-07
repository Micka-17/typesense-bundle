<?php

declare(strict_types=1);

namespace Micka17\TypesenseBundle\Tests\Dto;

use Micka17\TypesenseBundle\Dto\Paginator;
use PHPUnit\Framework\TestCase;

class PaginatorTest extends TestCase
{
    public function testPublicProperties(): void
    {
        $paginator = new Paginator(['item1', 'item2'], 2, 10, 100);

        $this->assertSame(['item1', 'item2'], $paginator->items);
        $this->assertSame(2, $paginator->currentPage);
        $this->assertSame(10, $paginator->perPage);
        $this->assertSame(100, $paginator->total);
    }

    public function testLastPageHook(): void
    {
        $this->assertSame(10, (new Paginator([], 1, 10, 100))->lastPage);
        $this->assertSame(10, (new Paginator([], 1, 10, 95))->lastPage);
        $this->assertSame(1, (new Paginator([], 1, 10, 0))->lastPage);  // max(1, …)
        $this->assertSame(1, (new Paginator([], 1, 10, 3))->lastPage);
    }

    public function testHasPreviousPageHook(): void
    {
        $this->assertFalse((new Paginator([], 1, 10, 100))->hasPreviousPage);
        $this->assertTrue((new Paginator([], 2, 10, 100))->hasPreviousPage);
    }

    public function testPreviousPageHook(): void
    {
        $this->assertNull((new Paginator([], 1, 10, 100))->previousPage);
        $this->assertSame(2, (new Paginator([], 3, 10, 100))->previousPage);
    }

    public function testHasNextPageHook(): void
    {
        $this->assertFalse((new Paginator([], 10, 10, 100))->hasNextPage);
        $this->assertTrue((new Paginator([], 9, 10, 100))->hasNextPage);
    }

    public function testNextPageHook(): void
    {
        $this->assertNull((new Paginator([], 10, 10, 100))->nextPage);
        $this->assertSame(9, (new Paginator([], 8, 10, 100))->nextPage);
    }

    public function testItemsAreReadonly(): void
    {
        $paginator = new Paginator(['a'], 1, 10, 1);

        $this->expectException(\Error::class);
        /** @phpstan-ignore-next-line */
        $paginator->items = ['b'];
    }
}
