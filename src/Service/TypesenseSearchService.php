<?php
// src/TypesenseBundle/Service/TypesenseSearchService.php

namespace Micka17\TypesenseBundle\Service;

use Micka17\TypesenseBundle\Dto\Result;
use Typesense\Client;

class TypesenseSearchService
{
    public function __construct(private readonly Client $client)
    {
    }

    public function search(string|array $collections, string $query, array $options = []): Result
    {
        $searchParams = array_merge([
            'q' => $query,
            'query_by' => 'name' // Uniquement un exemple, devrait être configurable
        ], $options);

        // Typesense attend un nom de collection en string
        $collectionName = is_array($collections) ? implode(',', $collections) : $collections;
        
        $result = $this->client->collections[$collectionName]->documents->search($searchParams);
        
        return Result::fromApiResponse($result);
    }
}