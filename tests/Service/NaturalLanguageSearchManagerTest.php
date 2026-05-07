<?php

namespace Micka17\TypesenseBundle\Tests\Service;

use Micka17\TypesenseBundle\Service\NaturalLanguageSearchManager;
use Micka17\TypesenseBundle\Service\TypesenseClient;
use PHPUnit\Framework\TestCase;

class NaturalLanguageSearchManagerTest extends TestCase
{
    public function testApplyConfiguredModelsInjectsModelId(): void
    {
        $client = $this->createMock(TypesenseClient::class);
        $manager = new NaturalLanguageSearchManager($client);

        $client->expects($this->once())
            ->method('createNlSearchModel')
            ->with(['model_name' => 'openai/gpt-4o-mini', 'id' => 'products-nl'])
            ->willReturn(['id' => 'products-nl']);

        $this->assertSame(['products-nl' => ['id' => 'products-nl']], $manager->applyConfiguredModels([
            'products-nl' => ['model_name' => 'openai/gpt-4o-mini'],
        ]));
    }
}
