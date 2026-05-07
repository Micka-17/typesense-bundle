<?php

declare(strict_types=1);

namespace Micka17\TypesenseBundle\Tests\Service;

use Micka17\TypesenseBundle\Service\AnalyticsManager;
use Micka17\TypesenseBundle\Service\TypesenseClient;
use PHPUnit\Framework\TestCase;

class AnalyticsManagerTest extends TestCase
{
    private TypesenseClient $client;
    private AnalyticsManager $manager;

    protected function setUp(): void
    {
        $this->client = $this->createMock(TypesenseClient::class);
        $this->manager = new AnalyticsManager($this->client);
    }

    public function testApplyConfiguredRulesInjectsRuleName(): void
    {
        $this->client->expects($this->once())
            ->method('createAnalyticsRules')
            ->with([[
                'type' => 'popular_queries',
                'params' => ['source' => ['collections' => ['products']]],
                'name' => 'popular_products',
            ]])
            ->willReturn([]);

        $this->manager->applyConfiguredRules([
            'popular_products' => [
                'type' => 'popular_queries',
                'params' => ['source' => ['collections' => ['products']]],
            ],
        ]);
    }

    public function testApplyConfiguredRulesCallsClientPerRule(): void
    {
        $this->client->expects($this->exactly(2))
            ->method('createAnalyticsRules')
            ->willReturn([]);

        $this->manager->applyConfiguredRules([
            'rule_a' => ['type' => 'popular_queries', 'params' => []],
            'rule_b' => ['type' => 'nohits_queries', 'params' => []],
        ]);
    }

    public function testApplyConfiguredRulesPreservesExistingName(): void
    {
        $this->client->expects($this->once())
            ->method('createAnalyticsRules')
            ->with([['name' => 'explicit-name', 'type' => 'counter', 'params' => []]])
            ->willReturn([]);

        $this->manager->applyConfiguredRules([
            'key' => ['name' => 'explicit-name', 'type' => 'counter', 'params' => []],
        ]);
    }

    public function testListRulesDelegatesToClient(): void
    {
        $expected = ['rules' => [['name' => 'r1']]];
        $this->client->expects($this->once())->method('listAnalyticsRules')->willReturn($expected);

        $this->assertSame($expected, $this->manager->listRules());
    }

    public function testRetrieveRuleDelegatesToClient(): void
    {
        $this->client->expects($this->once())
            ->method('retrieveAnalyticsRule')
            ->with('r1')
            ->willReturn(['name' => 'r1']);

        $this->assertSame(['name' => 'r1'], $this->manager->retrieveRule('r1'));
    }

    public function testUpdateRuleDelegatesToClient(): void
    {
        $this->client->expects($this->once())
            ->method('updateAnalyticsRule')
            ->with('r1', ['type' => 'log'])
            ->willReturn(['name' => 'r1', 'type' => 'log']);

        $this->manager->updateRule('r1', ['type' => 'log']);
    }

    public function testDeleteRuleDelegatesToClient(): void
    {
        $this->client->expects($this->once())
            ->method('deleteAnalyticsRule')
            ->with('r1')
            ->willReturn(['name' => 'r1']);

        $this->manager->deleteRule('r1');
    }

    public function testCreateEventDelegatesToClient(): void
    {
        $event = ['type' => 'click', 'name' => 'product_click', 'data' => ['doc_id' => '42']];
        $this->client->expects($this->once())
            ->method('createAnalyticsEvent')
            ->with($event)
            ->willReturn(['ok' => true]);

        $this->manager->createEvent($event);
    }
}
