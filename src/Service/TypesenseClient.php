<?php

namespace Micka17\TypesenseBundle\Service;

use Typesense\Client;
use Typesense\Exceptions\ObjectNotFound;

class TypesenseClient
{
    private Client $client;
    private bool $clusterEnabled;

    public function __construct(
        string $apiKey,
        array $nodes,
        string $readPreference = 'master_only',
        int $consistencyLevel = 1,
        bool $clusterEnabled = false
    ) {
        $this->client = new Client([
            'api_key' => $apiKey,
            'nodes' => array_map(fn($node) => [
                'host' => $node['host'],
                'port' => (int)$node['port'],
                'protocol' => $node['protocol'],
                'role' => $node['role']
            ], $nodes),
            'connection_timeout_seconds' => 2,
            'read_preference' => $readPreference,
            'consistency_level' => $consistencyLevel
        ]);
        $this->clusterEnabled = $clusterEnabled;
    }

    public function createCollection(array $schema): array
    {
        $retries = 3;
        $delay = 1000000; 
        
        for ($i = 0; $i < $retries; $i++) {
            try {
                return $this->client->collections->create($schema);
            } catch (\Exception $e) {
                if ($i === $retries - 1) {
                    throw $e;
                }
                usleep($delay);
                $delay *= 2;
            }
        }
        throw new \RuntimeException('Impossible de créer la collection après plusieurs tentatives');
    }

    public function deleteCollection(string $collectionName): array
    {
        $retries = 3;
        $delay = 1000000;
        
        for ($i = 0; $i < $retries; $i++) {
            try {
                return $this->client->collections[$collectionName]->delete();
            } catch (ObjectNotFound $e) {
                return ['success' => true];
            } catch (\Exception $e) {
                if ($i === $retries - 1) {
                    throw $e;
                }
                usleep($delay);
                $delay *= 2;
            }
        }
        throw new \RuntimeException('Impossible de supprimer la collection après plusieurs tentatives');
    }
    
    public function importDocuments(string $collectionName, array $documents, array $options = ['action' => 'upsert']): array
    {
        $batchSize = 100;
        $totalDocs = count($documents);
        $results = [];
        
        for ($i = 0; $i < $totalDocs; $i += $batchSize) {
            $batch = array_slice($documents, $i, $batchSize);
            $batchResults = $this->client->collections[$collectionName]->documents->import($batch, $options);
            $results = array_merge($results, $batchResults);
        }
        
        return $results;
    }
    
    public function retrieveCollection(string $collectionName): array
    {
        return $this->client->collections[$collectionName]->retrieve();
    }
    
    public function collectionExists(string $collectionName): bool
    {
        try {
            $this->retrieveCollection($collectionName);
            return true;
        } catch (ObjectNotFound) {
            return false;
        }
    }

    public function createOrUpdateDocument(string $collectionName, array $document): array
    {
        return $this->client->collections[$collectionName]->documents->upsert($document);
    }

    public function deleteDocument(string $collectionName, string $documentId): array
    {
        return $this->client->collections[$collectionName]->documents[$documentId]->delete();
    }

    public function retrieveDocument(string $collectionName, string $documentId): array
    {
        return $this->client->collections[$collectionName]->documents[$documentId]->retrieve();
    }

    public function documentExists(string $collectionName, string $documentId): bool
    {
        try {
            $this->retrieveDocument($collectionName, $documentId);
            return true;
        } catch (ObjectNotFound) {
            return false;
        }
    }

    public function search(string $collectionName, array $searchParameters): array
    {
        return $this->client->collections[$collectionName]->documents->search($searchParameters);
    }

    public function getClient(): Client
    {
        return $this->client;
    }

    public function isClusterEnabled(): bool
    {
        return $this->clusterEnabled;
    }
}