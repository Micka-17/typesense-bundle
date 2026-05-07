<?php

declare(strict_types=1);

namespace Micka17\TypesenseBundle\Tests\Service;

use Micka17\TypesenseBundle\Service\ConversationManager;
use Micka17\TypesenseBundle\Service\TypesenseClient;
use PHPUnit\Framework\TestCase;

class ConversationManagerTest extends TestCase
{
    private TypesenseClient $client;
    private ConversationManager $manager;

    protected function setUp(): void
    {
        $this->client = $this->createMock(TypesenseClient::class);
        $this->manager = new ConversationManager($this->client);
    }

    public function testApplyConfiguredModelsInjectsId(): void
    {
        $this->client->expects($this->once())
            ->method('createConversationModel')
            ->with(['model_name' => 'openai/gpt-4o', 'id' => 'support-bot'])
            ->willReturn(['id' => 'support-bot']);

        $result = $this->manager->applyConfiguredModels([
            'support-bot' => ['model_name' => 'openai/gpt-4o'],
        ]);

        $this->assertArrayHasKey('support-bot', $result);
    }

    public function testApplyConfiguredModelsPreservesExplicitId(): void
    {
        $this->client->expects($this->once())
            ->method('createConversationModel')
            ->with(['model_name' => 'openai/gpt-4o', 'id' => 'explicit-id'])
            ->willReturn(['id' => 'explicit-id']);

        $this->manager->applyConfiguredModels([
            'key' => ['model_name' => 'openai/gpt-4o', 'id' => 'explicit-id'],
        ]);
    }

    public function testListModels(): void
    {
        $expected = ['models' => [['id' => 'm1']]];
        $this->client->expects($this->once())->method('listConversationModels')->willReturn($expected);

        $this->assertSame($expected, $this->manager->listModels());
    }

    public function testRetrieveModel(): void
    {
        $this->client->expects($this->once())
            ->method('retrieveConversationModel')
            ->with('m1')
            ->willReturn(['id' => 'm1']);

        $this->manager->retrieveModel('m1');
    }

    public function testUpdateModel(): void
    {
        $this->client->expects($this->once())
            ->method('updateConversationModel')
            ->with('m1', ['model_name' => 'openai/gpt-4o-mini'])
            ->willReturn(['id' => 'm1']);

        $this->manager->updateModel('m1', ['model_name' => 'openai/gpt-4o-mini']);
    }

    public function testDeleteModel(): void
    {
        $this->client->expects($this->once())
            ->method('deleteConversationModel')
            ->with('m1')
            ->willReturn(['id' => 'm1']);

        $this->manager->deleteModel('m1');
    }
}
