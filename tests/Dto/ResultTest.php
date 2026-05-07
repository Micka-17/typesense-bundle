<?php

declare(strict_types=1);

namespace Micka17\TypesenseBundle\Tests\Dto;

use Micka17\TypesenseBundle\Dto\Result;
use PHPUnit\Framework\TestCase;

class ResultTest extends TestCase
{
    public function testFromApiResponseFullPayload(): void
    {
        $result = Result::fromApiResponse([
            'found' => 42,
            'took_ms' => 7,
            'hits' => [['document' => ['id' => '1']]],
            'facet_counts' => [['field_name' => 'genre']],
            'search_cutoff' => true,
        ]);

        $this->assertSame(42, $result->found);
        $this->assertSame(7, $result->tookMs);
        $this->assertCount(1, $result->hits);
        $this->assertCount(1, $result->facetCounts);
        $this->assertSame('1', $result->searchCutoff);
    }

    public function testFromApiResponseEmptyArray(): void
    {
        $result = Result::fromApiResponse([]);

        $this->assertSame(0, $result->found);
        $this->assertSame(0, $result->tookMs);
        $this->assertSame([], $result->hits);
        $this->assertSame([], $result->facetCounts);
        $this->assertNull($result->searchCutoff);
        $this->assertNull($result->conversation);
    }

    public function testIsEmptyHook(): void
    {
        $this->assertTrue(Result::fromApiResponse([])->isEmpty);
        $this->assertFalse(Result::fromApiResponse(['found' => 3])->isEmpty);
    }

    public function testConversationalHooks(): void
    {
        $result = Result::fromApiResponse([
            'found' => 1,
            'hits' => [],
            'conversation' => [
                'answer' => 'Paris is the capital of France.',
                'conversation_id' => 'conv-abc123',
                'query' => 'capital of france',
            ],
        ]);

        $this->assertTrue($result->isConversational);
        $this->assertSame('Paris is the capital of France.', $result->conversationAnswer);
        $this->assertSame('conv-abc123', $result->conversationId);
    }

    public function testNonConversationalResult(): void
    {
        $result = Result::fromApiResponse(['found' => 2]);

        $this->assertFalse($result->isConversational);
        $this->assertNull($result->conversationAnswer);
        $this->assertNull($result->conversationId);
    }

    public function testToArrayOmitsConversationWhenAbsent(): void
    {
        $data = Result::fromApiResponse(['found' => 1])->toArray();

        $this->assertArrayNotHasKey('conversation', $data);
    }

    public function testToArrayIncludesConversationWhenPresent(): void
    {
        $conv = ['answer' => 'Yes.', 'conversation_id' => 'c1'];
        $data = Result::fromApiResponse(['found' => 0, 'conversation' => $conv])->toArray();

        $this->assertSame($conv, $data['conversation']);
    }

    public function testJsonSerialize(): void
    {
        $result = Result::fromApiResponse(['found' => 5, 'took_ms' => 2]);
        $json = json_encode($result);

        $this->assertIsString($json);
        $decoded = json_decode($json, true);
        $this->assertSame(5, $decoded['found']);
        $this->assertSame(2, $decoded['took_ms']);
    }
}
