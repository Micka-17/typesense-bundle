<?php

namespace Micka17\TypesenseBundle\Tests\Service;

use Micka17\TypesenseBundle\Service\CurationSetManager;
use Micka17\TypesenseBundle\Service\TypesenseClient;
use PHPUnit\Framework\TestCase;

class CurationSetManagerTest extends TestCase
{
    public function testApplyConfiguredCurationSetsNormalizesItems(): void
    {
        $client = $this->createMock(TypesenseClient::class);
        $manager = new CurationSetManager($client);

        $client->expects($this->once())
            ->method('upsertCurationSet')
            ->with('products', [
                'items' => [
                    [
                        'id' => 'promote-iphone',
                        'rule' => ['query' => 'iphone'],
                        'includes' => [['id' => 'iphone-15', 'position' => 1]],
                    ],
                ],
            ])
            ->willReturn(['name' => 'products']);

        $result = $manager->applyConfiguredCurationSets([
            'products' => [
                'items' => [
                    'promote-iphone' => [
                        'rule' => ['query' => 'iphone'],
                        'includes' => [['id' => 'iphone-15', 'position' => 1]],
                    ],
                ],
            ],
        ]);

        $this->assertSame(['products' => ['name' => 'products']], $result);
    }
}
