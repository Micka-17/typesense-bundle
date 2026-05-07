<?php

declare(strict_types=1);

namespace Micka17\TypesenseBundle\Service;

use Micka17\TypesenseBundle\Dto\Paginator;
use Micka17\TypesenseBundle\Dto\Result;
use Exception;

class FinderService
{
    public function __construct(
        private readonly TypesenseClient $client,
        private readonly TypesenseErrorTracker $errorTracker,
    ) {}

    // --- Core search ---

    /**
     * @param array<string, mixed> $searchParameters
     */
    public function search(string $collectionName, array $searchParameters): Result
    {
        try {
            return Result::fromApiResponse(
                $this->client->search($collectionName, $searchParameters),
            );
        } catch (Exception $e) {
            $this->errorTracker->trackError('Typesense search failed.', ['collection' => $collectionName], $e);

            return Result::fromApiResponse([]);
        }
    }

    /**
     * @param array<string, mixed> $searchParameters
     */
    public function searchAndPaginate(string $collectionName, array $searchParameters, int $page = 1, int $perPage = 10): Paginator
    {
        $result = $this->search($collectionName, array_merge($searchParameters, [
            'page' => $page,
            'per_page' => $perPage,
        ]));

        return new Paginator(
            items: $result->hits,
            currentPage: $page,
            perPage: $perPage,
            total: $result->found,
        );
    }

    // --- Multi-search ---

    /**
     * @param array{searches: array<array<string, mixed>>, common_search_params?: array<string, mixed>} $searchRequests
     * @return Result[]
     */
    public function multiSearch(array $searchRequests): array
    {
        try {
            $raw = $this->client->multiSearch($searchRequests);

            return array_map(
                static fn(array $result) => Result::fromApiResponse($result),
                $raw['results'] ?? [],
            );
        } catch (Exception $e) {
            $this->errorTracker->trackError('Typesense multi-search failed.', [], $e);

            return [];
        }
    }

    // --- Preset search ---

    /**
     * @param array<string, mixed> $extraParams
     */
    public function searchWithPreset(string $presetName, array $extraParams = []): Result
    {
        try {
            $raw = $this->client->searchWithPreset($presetName, $extraParams);

            return Result::fromApiResponse(is_array($raw) ? $raw : []);
        } catch (Exception $e) {
            $this->errorTracker->trackError('Typesense preset search failed.', ['preset' => $presetName], $e);

            return Result::fromApiResponse([]);
        }
    }

    // --- Union search (v30+) ---

    /**
     * Search across multiple collections and merge results into a single Result.
     *
     * Typesense v30 union search via multiSearch with union=true.
     * Each hit in the result includes a `_collection` field identifying its source collection.
     *
     * @param array<array<string, mixed>> $searches  Per-collection search configs, each must include 'collection' and 'q'.
     * @param array<string, mixed>        $commonParams  Shared params applied to all searches (e.g. per_page, filter_by).
     */
    public function unionSearch(array $searches, array $commonParams = []): Result
    {
        try {
            $request = ['searches' => $searches];
            if ($commonParams !== []) {
                $request['common_search_params'] = $commonParams;
            }

            $raw = $this->client->multiSearch($request, ['union' => 'true']);

            // Union search returns a flat merged result, not an array of per-collection results.
            $merged = $raw['results'][0] ?? $raw;

            return Result::fromApiResponse($merged);
        } catch (Exception $e) {
            $this->errorTracker->trackError('Typesense union search failed.', [], $e);

            return Result::fromApiResponse([]);
        }
    }

    // --- MMR diversification (v30+) ---

    /**
     * Vector search with MMR (Maximal Marginal Relevance) diversification.
     *
     * Pass mmr_lambda (0 = max diversity, 1 = max relevance) and the embedding field.
     * searchParameters must already include 'q' and a vector_query or embed field.
     *
     * @param array<string, mixed> $searchParameters
     * @param float       $mmrLambda         Balance between relevance (1.0) and diversity (0.0).
     * @param string|null $mmrEmbeddingField  Embedding field to use; inferred by Typesense if null.
     */
    public function searchWithDiversification(
        string $collectionName,
        array $searchParameters,
        float $mmrLambda = 0.5,
        ?string $mmrEmbeddingField = null,
    ): Result {
        $searchParameters['mmr_lambda'] = $mmrLambda;

        if ($mmrEmbeddingField !== null) {
            $searchParameters['mmr_embedding_field'] = $mmrEmbeddingField;
        }

        return $this->search($collectionName, $searchParameters);
    }

    // --- Conversational / RAG search (v30+) ---

    /**
     * RAG-style search: Typesense answers the query using a conversation model.
     *
     * Returns a Result with $result->conversationAnswer and $result->conversationId
     * populated. Pass the returned conversationId as $conversationId on follow-up
     * queries to maintain context.
     *
     * @param array<string, mixed> $searchParameters
     * @param string      $conversationModelId  ID of the conversation model configured in Typesense.
     * @param string|null $conversationId       ID of an existing conversation to continue (optional).
     */
    public function conversationalSearch(
        string $collectionName,
        array $searchParameters,
        string $conversationModelId,
        ?string $conversationId = null,
    ): Result {
        $searchParameters['conversation'] = true;
        $searchParameters['conversation_model_id'] = $conversationModelId;

        if ($conversationId !== null) {
            $searchParameters['conversation_id'] = $conversationId;
        }

        return $this->search($collectionName, $searchParameters);
    }

    // --- Natural language search (v30+) ---

    /**
     * Search using a natural language query processed by an NL search model.
     *
     * Typesense translates $query into structured search parameters server-side.
     * The 'q' param can be omitted or set to '*'; $query drives the intent.
     *
     * @param string               $nlSearchModelId  ID of the NL search model configured in Typesense.
     * @param array<string, mixed> $extraParams      Additional raw search parameters (filter_by, per_page, …).
     */
    public function naturalLanguageSearch(
        string $collectionName,
        string $query,
        string $nlSearchModelId,
        array $extraParams = [],
    ): Result {
        $params = array_merge($extraParams, [
            'q' => '*',
            'natural_language_query' => $query,
            'nl_search_model_id' => $nlSearchModelId,
        ]);

        return $this->search($collectionName, $params);
    }
}
