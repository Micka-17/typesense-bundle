<?php

declare(strict_types=1);

namespace Micka17\TypesenseBundle\Tests\Service;

use Micka17\TypesenseBundle\Service\PresetManager;
use Micka17\TypesenseBundle\Service\TypesenseClient;
use PHPUnit\Framework\TestCase;

class PresetManagerTest extends TestCase
{
    private TypesenseClient $client;
    private PresetManager $manager;

    protected function setUp(): void
    {
        $this->client = $this->createMock(TypesenseClient::class);
        $this->manager = new PresetManager($this->client);
    }

    public function testApplyConfiguredPresets(): void
    {
        $config = ['q' => '*', 'per_page' => 10];
        $this->client->expects($this->once())
            ->method('upsertPreset')
            ->with('default-search', $config)
            ->willReturn(['name' => 'default-search']);

        $result = $this->manager->applyConfiguredPresets(['default-search' => $config]);

        $this->assertArrayHasKey('default-search', $result);
    }

    public function testUpsertPreset(): void
    {
        $this->client->expects($this->once())
            ->method('upsertPreset')
            ->with('my-preset', ['q' => '*'])
            ->willReturn(['name' => 'my-preset']);

        $this->manager->upsertPreset('my-preset', ['q' => '*']);
    }

    public function testListPresets(): void
    {
        $expected = ['presets' => [['name' => 'p1']]];
        $this->client->expects($this->once())->method('listPresets')->willReturn($expected);

        $this->assertSame($expected, $this->manager->listPresets());
    }

    public function testRetrievePreset(): void
    {
        $this->client->expects($this->once())
            ->method('retrievePreset')
            ->with('p1')
            ->willReturn(['name' => 'p1']);

        $this->manager->retrievePreset('p1');
    }

    public function testDeletePreset(): void
    {
        $this->client->expects($this->once())
            ->method('deletePreset')
            ->with('p1')
            ->willReturn(['name' => 'p1']);

        $this->manager->deletePreset('p1');
    }

    public function testApplyEmptyPresets(): void
    {
        $this->client->expects($this->never())->method('upsertPreset');

        $this->assertSame([], $this->manager->applyConfiguredPresets([]));
    }
}
