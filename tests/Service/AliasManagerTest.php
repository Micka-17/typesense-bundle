<?php

declare(strict_types=1);

namespace Micka17\TypesenseBundle\Tests\Service;

use Micka17\TypesenseBundle\Service\AliasManager;
use Micka17\TypesenseBundle\Service\TypesenseClient;
use PHPUnit\Framework\TestCase;

class AliasManagerTest extends TestCase
{
    private TypesenseClient $client;
    private AliasManager $manager;

    protected function setUp(): void
    {
        $this->client = $this->createMock(TypesenseClient::class);
        $this->manager = new AliasManager($this->client);
    }

    public function testUpsertAlias(): void
    {
        $this->client->expects($this->once())
            ->method('upsertAlias')
            ->with('products', 'products_v2')
            ->willReturn(['name' => 'products', 'collection_name' => 'products_v2']);

        $result = $this->manager->upsertAlias('products', 'products_v2');

        $this->assertSame('products_v2', $result['collection_name']);
    }

    public function testListAliases(): void
    {
        $expected = ['aliases' => [['name' => 'products', 'collection_name' => 'products_v1']]];
        $this->client->expects($this->once())->method('listAliases')->willReturn($expected);

        $this->assertSame($expected, $this->manager->listAliases());
    }

    public function testRetrieveAlias(): void
    {
        $expected = ['name' => 'products', 'collection_name' => 'products_v1'];
        $this->client->expects($this->once())
            ->method('retrieveAlias')
            ->with('products')
            ->willReturn($expected);

        $this->assertSame($expected, $this->manager->retrieveAlias('products'));
    }

    public function testDeleteAlias(): void
    {
        $this->client->expects($this->once())
            ->method('deleteAlias')
            ->with('products')
            ->willReturn(['name' => 'products']);

        $this->manager->deleteAlias('products');
    }

    public function testSwapAliasReturnsPreviousCollection(): void
    {
        $this->client->expects($this->once())
            ->method('retrieveAlias')
            ->with('products')
            ->willReturn(['name' => 'products', 'collection_name' => 'products_v1']);

        $this->client->expects($this->once())
            ->method('upsertAlias')
            ->with('products', 'products_v2')
            ->willReturn(['name' => 'products', 'collection_name' => 'products_v2']);

        $previous = $this->manager->swapAlias('products', 'products_v2');

        $this->assertSame('products_v1', $previous);
    }

    public function testSwapAliasReturnsNullWhenAliasDidNotExist(): void
    {
        $this->client->expects($this->once())
            ->method('retrieveAlias')
            ->with('products')
            ->willThrowException(new \RuntimeException('Not found'));

        $this->client->expects($this->once())
            ->method('upsertAlias')
            ->with('products', 'products_v1')
            ->willReturn(['name' => 'products', 'collection_name' => 'products_v1']);

        $previous = $this->manager->swapAlias('products', 'products_v1');

        $this->assertNull($previous);
    }
}
