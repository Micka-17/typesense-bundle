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
        private readonly TypesenseErrorTracker $errorTracker
    ) {
    }

    public function search(string $collectionName, array $searchParameters): Result
    {
        try {
            $searchResult = $this->client->getOperations()->collections[$collectionName]->documents->search($searchParameters);

            return new Result($searchResult);
        } catch (Exception $e) {
            $this->errorTracker->handle($e);

            return new Result([]);
        }
    }

    /**
     * @param array{searches: array<array<string, mixed>>, common_search_params?: array<string, mixed>} $searchRequests
     * @return Result[]
     */
    public function multiSearch(array $searchRequests): array
    {
        try {
            $results = $this->client->getOperations()->multiSearch->perform($searchRequests);

            $dtoResults = [];
            foreach ($results['results'] ?? [] as $result) {
                $dtoResults[] = new Result($result);
            }

            return $dtoResults;
        } catch (Exception $e) {
            $this->errorTracker->handle($e);

            return [];
        }
    }

    public function searchAndPaginate(string $collectionName, array $searchParameters, int $page = 1, int $perPage = 10): Paginator
    {
        $searchParameters['page'] = $page;
        $searchParameters['per_page'] = $perPage;

        $result = $this->search($collectionName, $searchParameters);

        return new Paginator(
            $result->getHits(),
            $page,
            $perPage,
            $result->getTotal()
        );
    }
}
