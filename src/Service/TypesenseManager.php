<?php
// src/TypesenseBundle/Service/TypesenseManager.php

namespace Micka17\TypesenseBundle\Service;

use Typesense\Client;
use Typesense\Exceptions\ObjectNotFound;

class TypesenseManager
{
    public function __construct(private readonly Client $client)
    {
    }

    public function createCollection(array $schema): array
    {
        return $this->client->collections->create($schema);
    }

    public function deleteCollection(string $name): array
    {
        return $this->client->collections[$name]->delete();
    }

    public function collectionExists(string $name): bool
    {
        try {
            $this->client->collections[$name]->retrieve();
            return true;
        } catch (ObjectNotFound) {
            return false;
        }
    }

    public function createOrUpdateDocument(string $collection, array $document): array
    {
        return $this->client->collections[$collection]->documents->upsert($document);
    }

    public function deleteDocument(string $collection, string $id): array
    {
        return $this->client->collections[$collection]->documents[$id]->delete();
    }

    public function documentExists(string $collection, string $id): bool
    {
        try {
            $this->client->collections[$collection]->documents[$id]->retrieve();
            return true;
        } catch (ObjectNotFound) {
            return false;
        }
    }
    
    public function getClient(): Client
    {
        return $this->client;
    }
}