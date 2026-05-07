<?php

declare(strict_types=1);

namespace Micka17\TypesenseBundle\Tests\Service;

use Micka17\TypesenseBundle\Service\KeysManager;
use Micka17\TypesenseBundle\Service\TypesenseClient;
use PHPUnit\Framework\TestCase;

class KeysManagerTest extends TestCase
{
    private TypesenseClient $client;
    private KeysManager $manager;

    protected function setUp(): void
    {
        $this->client  = $this->createMock(TypesenseClient::class);
        $this->manager = new KeysManager($this->client);
    }

    public function testCreateKeyReturnsResultWithValue(): void
    {
        $config   = ['description' => 'Search only', 'actions' => ['documents:search'], 'collections' => ['products']];
        $expected = ['id' => 1, 'description' => 'Search only', 'value' => 'abc123secretkey'];

        $this->client->expects($this->once())
            ->method('createKey')
            ->with($config)
            ->willReturn($expected);

        $result = $this->manager->createKey($config);

        $this->assertSame('abc123secretkey', $result['value']);
        $this->assertSame(1, $result['id']);
    }

    public function testListKeysReturnsResponse(): void
    {
        $expected = ['keys' => [['id' => 1, 'description' => 'Read only'], ['id' => 2, 'description' => 'Admin']]];

        $this->client->expects($this->once())->method('listKeys')->willReturn($expected);

        $this->assertSame($expected, $this->manager->listKeys());
    }

    public function testRetrieveKey(): void
    {
        $expected = ['id' => 42, 'description' => 'Search key', 'actions' => ['documents:search']];

        $this->client->expects($this->once())
            ->method('retrieveKey')
            ->with(42)
            ->willReturn($expected);

        $result = $this->manager->retrieveKey(42);
        $this->assertSame(42, $result['id']);
    }

    public function testDeleteKey(): void
    {
        $this->client->expects($this->once())
            ->method('deleteKey')
            ->with(42)
            ->willReturn(['id' => 42]);

        $this->manager->deleteKey(42);
    }
}
