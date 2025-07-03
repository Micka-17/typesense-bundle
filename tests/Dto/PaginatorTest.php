<?php

declare(strict_types=1);

namespace Micka17\TypesenseBundle\Tests\Dto;

use Micka17\TypesenseBundle\Dto\Paginator;
use PHPUnit\Framework\TestCase;

class PaginatorTest extends TestCase
{
    public function testGetters()
    {
        $paginator = new Paginator(['item1', 'item2'], 2, 10, 100);

        $this->assertSame(['item1', 'item2'], $paginator->getItems());
        $this->assertSame(2, $paginator->getCurrentPage());
        $this->assertSame(10, $paginator->getPerPage());
        $this->assertSame(100, $paginator->getTotal());
    }

    public function testGetLastPage()
    {
        $paginator = new Paginator([], 1, 10, 100);
        $this->assertSame(10, $paginator->getLastPage());

        $paginator = new Paginator([], 1, 10, 95);
        $this->assertSame(10, $paginator->getLastPage());

        $paginator = new Paginator([], 1, 10, 0);
        $this->assertSame(0, $paginator->getLastPage());
    }

    public function testHasPreviousPage()
    {
        $paginator = new Paginator([], 1, 10, 100);
        $this->assertFalse($paginator->hasPreviousPage());

        $paginator = new Paginator([], 2, 10, 100);
        $this->assertTrue($paginator->hasPreviousPage());
    }

    public function testGetPreviousPage()
    {
        $paginator = new Paginator([], 1, 10, 100);
        $this->assertNull($paginator->getPreviousPage());

        $paginator = new Paginator([], 3, 10, 100);
        $this->assertSame(2, $paginator->getPreviousPage());
    }

    public function testHasNextPage()
    {
        $paginator = new Paginator([], 10, 10, 100);
        $this->assertFalse($paginator->hasNextPage());

        $paginator = new Paginator([], 9, 10, 100);
        $this->assertTrue($paginator->hasNextPage());
    }

    public function testGetNextPage()
    {
        $paginator = new Paginator([], 10, 10, 100);
        $this->assertNull($paginator->getNextPage());

        $paginator = new Paginator([], 8, 10, 100);
        $this->assertSame(9, $paginator->getNextPage());
    }
}
