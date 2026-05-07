<?php

declare(strict_types=1);

namespace Micka17\TypesenseBundle\Tests\Service;

use Micka17\TypesenseBundle\Dto\Paginator;
use Micka17\TypesenseBundle\Dto\Result;
use Micka17\TypesenseBundle\Service\FinderService;
use Micka17\TypesenseBundle\Service\TypesenseClient;
use Micka17\TypesenseBundle\Service\TypesenseErrorTracker;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;

#[AllowMockObjectsWithoutExpectations]
class FinderServiceTest extends TestCase
{
    private TypesenseClient $client;
    private TypesenseErrorTracker $tracker;
    private FinderService $finder;

    protected function setUp(): void
    {
        $this->client = $this->createMock(TypesenseClient::class);
        $this->tracker = $this->createMock(TypesenseErrorTracker::class);
        $this->finder = new FinderService($this->client, $this->tracker);
    }

    // --- search ---

    public function testSearchReturnsResult(): void
    {
        $this->client->method('search')->willReturn([
            'found' => 2,
            'took_ms' => 3,
            'hits' => [['document' => ['id' => '1']], ['document' => ['id' => '2']]],
        ]);

        $result = $this->finder->search('products', ['q' => 'laptop']);

        $this->assertInstanceOf(Result::class, $result);
        $this->assertSame(2, $result->found);
        $this->assertCount(2, $result->hits);
    }

    public function testSearchReturnsEmptyResultOnException(): void
    {
        $this->client->method('search')->willThrowException(new \RuntimeException('Connection refused'));
        $this->tracker->expects($this->once())->method('trackError');

        $result = $this->finder->search('products', ['q' => 'laptop']);

        $this->assertTrue($result->isEmpty);
    }

    // --- multiSearch ---

    public function testMultiSearchReturnsArrayOfResults(): void
    {
        $this->client->method('multiSearch')->willReturn([
            'results' => [
                ['found' => 1, 'hits' => []],
                ['found' => 3, 'hits' => []],
            ],
        ]);

        $results = $this->finder->multiSearch(['searches' => [[], []]]);

        $this->assertCount(2, $results);
        $this->assertInstanceOf(Result::class, $results[0]);
        $this->assertSame(1, $results[0]->found);
        $this->assertSame(3, $results[1]->found);
    }

    public function testMultiSearchReturnsEmptyArrayOnException(): void
    {
        $this->client->method('multiSearch')->willThrowException(new \RuntimeException());
        $this->tracker->expects($this->once())->method('trackError');

        $this->assertSame([], $this->finder->multiSearch(['searches' => []]));
    }

    // --- searchAndPaginate ---

    public function testSearchAndPaginateReturnsPaginator(): void
    {
        $this->client->method('search')->willReturn([
            'found' => 25,
            'hits' => array_fill(0, 10, ['document' => []]),
        ]);

        $paginator = $this->finder->searchAndPaginate('products', ['q' => '*'], page: 2, perPage: 10);

        $this->assertInstanceOf(Paginator::class, $paginator);
        $this->assertSame(2, $paginator->currentPage);
        $this->assertSame(10, $paginator->perPage);
        $this->assertSame(25, $paginator->total);
        $this->assertSame(3, $paginator->lastPage);
        $this->assertCount(10, $paginator->items);
    }

    public function testSearchAndPaginateInjectsPageParams(): void
    {
        $this->client->expects($this->once())
            ->method('search')
            ->with('products', $this->callback(static fn(array $p) => $p['page'] === 3 && $p['per_page'] === 5))
            ->willReturn(['found' => 0, 'hits' => []]);

        $this->finder->searchAndPaginate('products', ['q' => '*'], page: 3, perPage: 5);
    }

    // --- searchWithPreset ---

    public function testSearchWithPresetReturnsResult(): void
    {
        $this->client->expects($this->once())->method('searchWithPreset')
            ->with('default-search', [])
            ->willReturn(['found' => 5, 'hits' => []]);

        $result = $this->finder->searchWithPreset('default-search');

        $this->assertSame(5, $result->found);
    }

    public function testSearchWithPresetReturnsEmptyResultOnException(): void
    {
        $this->client->method('searchWithPreset')->willThrowException(new \RuntimeException());
        $this->tracker->expects($this->once())->method('trackError');

        $this->assertTrue($this->finder->searchWithPreset('bad-preset')->isEmpty);
    }

    // --- unionSearch ---

    public function testUnionSearchPassesUnionQueryParam(): void
    {
        $this->client->expects($this->once())
            ->method('multiSearch')
            ->with(
                $this->callback(static fn(array $req) => isset($req['searches'])),
                ['union' => 'true'],
            )
            ->willReturn(['results' => [['found' => 10, 'hits' => []]]]);

        $result = $this->finder->unionSearch([
            ['collection' => 'books', 'q' => 'harry'],
            ['collection' => 'movies', 'q' => 'harry'],
        ]);

        $this->assertSame(10, $result->found);
    }

    public function testUnionSearchWithCommonParams(): void
    {
        $this->client->expects($this->once())
            ->method('multiSearch')
            ->with(
                $this->callback(static fn(array $req) =>
                    isset($req['common_search_params']['per_page'])
                    && $req['common_search_params']['per_page'] === 20
                ),
                ['union' => 'true'],
            )
            ->willReturn(['results' => [['found' => 0, 'hits' => []]]]);

        $this->finder->unionSearch(
            [['collection' => 'books', 'q' => '*']],
            ['per_page' => 20],
        );
    }

    public function testUnionSearchReturnsEmptyOnException(): void
    {
        $this->client->method('multiSearch')->willThrowException(new \RuntimeException());
        $this->tracker->expects($this->once())->method('trackError');

        $this->assertTrue($this->finder->unionSearch([])->isEmpty);
    }

    // --- searchWithDiversification ---

    public function testSearchWithDiversificationInjectsMmrParams(): void
    {
        $this->client->expects($this->once())
            ->method('search')
            ->with('products', $this->callback(
                static fn(array $p) => $p['mmr_lambda'] === 0.3 && $p['mmr_embedding_field'] === 'embedding',
            ))
            ->willReturn(['found' => 0, 'hits' => []]);

        $this->finder->searchWithDiversification(
            'products',
            ['q' => 'laptop', 'vector_query' => 'embedding:([…])'],
            mmrLambda: 0.3,
            mmrEmbeddingField: 'embedding',
        );
    }

    public function testSearchWithDiversificationDefaultLambda(): void
    {
        $this->client->expects($this->once())
            ->method('search')
            ->with('products', $this->callback(static fn(array $p) => $p['mmr_lambda'] === 0.5))
            ->willReturn(['found' => 0, 'hits' => []]);

        $this->finder->searchWithDiversification('products', ['q' => '*']);
    }

    // --- conversationalSearch ---

    public function testConversationalSearchInjectsConversationParams(): void
    {
        $this->client->expects($this->once())
            ->method('search')
            ->with('products', $this->callback(static fn(array $p) =>
                $p['conversation'] === true
                && $p['conversation_model_id'] === 'gpt4-model'
                && !isset($p['conversation_id'])
            ))
            ->willReturn(['found' => 1, 'hits' => [], 'conversation' => ['answer' => 'Yes.', 'conversation_id' => 'c1']]);

        $result = $this->finder->conversationalSearch('products', ['q' => 'best laptop'], 'gpt4-model');

        $this->assertTrue($result->isConversational);
        $this->assertSame('Yes.', $result->conversationAnswer);
    }

    public function testConversationalSearchWithExistingConversationId(): void
    {
        $this->client->expects($this->once())
            ->method('search')
            ->with('products', $this->callback(static fn(array $p) =>
                $p['conversation_id'] === 'conv-123'
            ))
            ->willReturn(['found' => 0, 'hits' => []]);

        $this->finder->conversationalSearch('products', ['q' => 'follow-up'], 'gpt4-model', 'conv-123');
    }

    // --- naturalLanguageSearch ---

    public function testNaturalLanguageSearchInjectsNlParams(): void
    {
        $this->client->expects($this->once())
            ->method('search')
            ->with('products', $this->callback(static fn(array $p) =>
                $p['natural_language_query'] === 'laptops under 1000 euros'
                && $p['nl_search_model_id'] === 'products-nl'
                && $p['q'] === '*'
            ))
            ->willReturn(['found' => 0, 'hits' => []]);

        $this->finder->naturalLanguageSearch(
            'products',
            'laptops under 1000 euros',
            'products-nl',
        );
    }

    public function testNaturalLanguageSearchMergesExtraParams(): void
    {
        $this->client->expects($this->once())
            ->method('search')
            ->with('products', $this->callback(static fn(array $p) =>
                $p['filter_by'] === 'available:true'
                && $p['per_page'] === 5
            ))
            ->willReturn(['found' => 0, 'hits' => []]);

        $this->finder->naturalLanguageSearch(
            'products',
            'cheap laptops',
            'products-nl',
            ['filter_by' => 'available:true', 'per_page' => 5],
        );
    }
}
