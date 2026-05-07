<?php

namespace Micka17\TypesenseBundle\Tests\Service;

use Micka17\TypesenseBundle\Service\SynonymSetManager;
use Micka17\TypesenseBundle\Service\TypesenseClient;
use PHPUnit\Framework\TestCase;

class SynonymSetManagerTest extends TestCase
{
    public function testApplyConfiguredSynonymSetsNormalizesItems(): void
    {
        $client = $this->createMock(TypesenseClient::class);
        $manager = new SynonymSetManager($client);

        $client->expects($this->once())
            ->method('upsertSynonymSet')
            ->with('products', [
                'items' => [
                    ['id' => 'size', 'synonyms' => ['large', 'big']],
                    ['id' => 'color', 'synonyms' => ['red', 'blue'], 'root' => 'primary_color'],
                ],
            ])
            ->willReturn(['name' => 'products']);

        $result = $manager->applyConfiguredSynonymSets([
            'products' => [
                'items' => [
                    'size' => ['synonyms' => ['large', 'big']],
                    'color' => ['root' => 'primary_color', 'synonyms' => ['red', 'blue']],
                ],
            ],
        ]);

        $this->assertSame(['products' => ['name' => 'products']], $result);
    }
}
