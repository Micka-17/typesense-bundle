<?php

declare(strict_types=1);

namespace Micka17\TypesenseBundle\Service;

use Typesense\Client;
use Typesense\Exceptions\ObjectNotFound;

class TypesenseClient
{
    private readonly Client $client;
    private readonly bool $clusterEnabled;

    /**
     * @param array<int, array<string, mixed>> $nodes
     */
    public function __construct(
        string $apiKey,
        array $nodes,
        string $readPreference = 'leader_only',
        int $consistencyLevel = 1,
        bool $clusterEnabled = false,
    ) {
        $this->client = new Client([
            'api_key' => $apiKey,
            'nodes' => array_map(static fn(array $node) => [
                'host' => $node['host'],
                'port' => (int) $node['port'],
                'protocol' => $node['protocol'],
                'role' => $node['role'],
            ], $nodes),
            'connection_timeout_seconds' => 2,
            'read_preference' => $readPreference,
            'consistency_level' => $consistencyLevel,
        ]);
        $this->clusterEnabled = $clusterEnabled;
    }

    // Collections

    /**
     * @param array<string, mixed> $schema
     * @return array<string, mixed>
     */
    public function createCollection(array $schema): array
    {
        return $this->withRetry(fn() => $this->client->collections->create($schema));
    }

    /**
     * @return array<string, mixed>
     */
    public function deleteCollection(string $collectionName): array
    {
        return $this->withRetry(function () use ($collectionName) {
            try {
                return $this->client->collections[$collectionName]->delete();
            } catch (ObjectNotFound) {
                return ['success' => true];
            }
        });
    }

    /**
     * @return array<string, mixed>
     */
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

    // Documents

    /**
     * @param array<int, array<string, mixed>> $documents
     * @param array<string, mixed>             $options
     * @return array<int, array<string, mixed>>
     */
    public function importDocuments(string $collectionName, array $documents, array $options = ['action' => 'upsert']): array
    {
        $results = [];
        foreach (array_chunk($documents, 100) as $batch) {
            $results = array_merge(
                $results,
                $this->client->collections[$collectionName]->documents->import($batch, $options),
            );
        }

        return $results;
    }

    /**
     * @param array<string, mixed> $document
     * @return array<string, mixed>
     */
    public function createOrUpdateDocument(string $collectionName, array $document): array
    {
        return $this->client->collections[$collectionName]->documents->upsert($document);
    }

    /**
     * @return array<string, mixed>
     */
    public function deleteDocument(string $collectionName, string $documentId): array
    {
        return $this->client->collections[$collectionName]->documents[$documentId]->delete();
    }

    /**
     * @return array<string, mixed>
     */
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

    // Search

    /**
     * @param array<string, mixed> $searchParameters
     * @return array<string, mixed>
     */
    public function search(string $collectionName, array $searchParameters): array
    {
        return $this->client->collections[$collectionName]->documents->search($searchParameters);
    }

    /**
     * @param array<string, mixed> $searchRequests
     * @param array<string, mixed> $queryParameters
     * @return array<string, mixed>
     */
    public function multiSearch(array $searchRequests, array $queryParameters = []): array
    {
        return $this->client->multiSearch->perform($searchRequests, $queryParameters);
    }

    // Synonym Sets

    /**
     * @param array<string, mixed> $config
     * @return array<string, mixed>
     */
    public function upsertSynonymSet(string $name, array $config): array
    {
        return $this->client->synonymSets->upsert($name, $config);
    }

    /**
     * @return array<string, mixed>
     */
    public function listSynonymSets(): array
    {
        return $this->client->synonymSets->retrieve();
    }

    /**
     * @return array<string, mixed>
     */
    public function retrieveSynonymSet(string $name): array
    {
        return $this->client->synonymSets[$name]->retrieve();
    }

    /**
     * @return array<string, mixed>
     */
    public function deleteSynonymSet(string $name): array
    {
        return $this->client->synonymSets[$name]->delete();
    }

    // Curation Sets

    /**
     * @param array<string, mixed> $config
     * @return array<string, mixed>
     */
    public function upsertCurationSet(string $name, array $config): array
    {
        return $this->client->curationSets->upsert($name, $config);
    }

    /**
     * @return array<string, mixed>
     */
    public function listCurationSets(): array
    {
        return $this->client->curationSets->retrieve();
    }

    /**
     * @return array<string, mixed>
     */
    public function retrieveCurationSet(string $name): array
    {
        return $this->client->curationSets[$name]->retrieve();
    }

    /**
     * @return array<string, mixed>
     */
    public function deleteCurationSet(string $name): array
    {
        return $this->client->curationSets[$name]->delete();
    }

    /**
     * @param array<string, mixed> $config
     * @return array<string, mixed>
     */
    public function upsertCurationSetItem(string $setName, string $itemId, array $config): array
    {
        return $this->client->curationSets[$setName]->getItems()[$itemId]->upsert($config);
    }

    /**
     * @return array<string, mixed>
     */
    public function listCurationSetItems(string $setName): array
    {
        return $this->client->curationSets[$setName]->getItems()->retrieve();
    }

    // Presets

    /**
     * @param array<string, mixed> $config
     * @return array<string, mixed>
     */
    public function upsertPreset(string $name, array $config): array
    {
        return $this->client->presets->upsert($name, $config);
    }

    /**
     * @return array<string, mixed>
     */
    public function listPresets(): array
    {
        return $this->client->presets->retrieve();
    }

    /**
     * @return array<string, mixed>
     */
    public function retrievePreset(string $name): array
    {
        return $this->client->presets[$name]->retrieve();
    }

    /**
     * @return array<string, mixed>
     */
    public function deletePreset(string $name): array
    {
        return $this->client->presets[$name]->delete();
    }

    /**
     * @param array<string, mixed> $extraParams
     * @return array<string, mixed>|string
     */
    public function searchWithPreset(string $name, array $extraParams = []): array|string
    {
        return $this->client->presets->searchWithPreset($name);
    }

    // Stemming

    /**
     * @param array<mixed>|string $words
     * @return array<string, mixed>|string
     */
    public function upsertStemmingDictionary(string $name, array|string $words): array|string
    {
        return $this->client->stemming->dictionaries()->upsert($name, $words);
    }

    /**
     * @return array<string, mixed>
     */
    public function listStemmingDictionaries(): array
    {
        return $this->client->stemming->dictionaries()->retrieve();
    }

    /**
     * @return array<string, mixed>
     */
    public function retrieveStemmingDictionary(string $name): array
    {
        return $this->client->stemming->dictionaries()[$name]->retrieve();
    }

    /**
     * @return array<string, mixed>
     */
    public function deleteStemmingDictionary(string $name): array
    {
        return $this->client->stemming->dictionaries()[$name]->delete();
    }

    // Analytics

    /**
     * @param array<mixed> $rules
     * @return array<string, mixed>
     */
    public function createAnalyticsRules(array $rules): array
    {
        return $this->client->analytics->rules()->create($rules);
    }

    /** @return array<string, mixed> */
    public function listAnalyticsRules(): array
    {
        return $this->client->analytics->rules()->retrieve();
    }

    /** @return array<string, mixed> */
    public function retrieveAnalyticsRule(string $name): array
    {
        return $this->client->analytics->rules()[$name]->retrieve();
    }

    /**
     * @param array<string, mixed> $config
     * @return array<string, mixed>
     */
    public function updateAnalyticsRule(string $name, array $config): array
    {
        return $this->client->analytics->rules()[$name]->update($config);
    }

    /** @return array<string, mixed> */
    public function deleteAnalyticsRule(string $name): array
    {
        return $this->client->analytics->rules()[$name]->delete();
    }

    /**
     * @param array<string, mixed> $event
     * @return array<string, mixed>
     */
    public function createAnalyticsEvent(array $event): array
    {
        return $this->client->analytics->events()->create($event);
    }

    // Natural Language Search

    /**
     * @param array<string, mixed> $config
     * @return array<string, mixed>
     */
    public function createNlSearchModel(array $config): array
    {
        return $this->client->nlSearchModels->create($config);
    }

    /** @return array<string, mixed> */
    public function listNlSearchModels(): array
    {
        return $this->client->nlSearchModels->retrieve();
    }

    /** @return array<string, mixed> */
    public function retrieveNlSearchModel(string $id): array
    {
        return $this->client->nlSearchModels[$id]->retrieve();
    }

    /**
     * @param array<string, mixed> $config
     * @return array<string, mixed>
     */
    public function updateNlSearchModel(string $id, array $config): array
    {
        return $this->client->nlSearchModels[$id]->update($config);
    }

    /** @return array<string, mixed> */
    public function deleteNlSearchModel(string $id): array
    {
        return $this->client->nlSearchModels[$id]->delete();
    }

    // Conversations

    /**
     * @param array<string, mixed> $config
     * @return array<string, mixed>
     */
    public function createConversationModel(array $config): array
    {
        return $this->client->conversations->getModels()->create($config);
    }

    /** @return array<string, mixed> */
    public function listConversationModels(): array
    {
        return $this->client->conversations->getModels()->retrieve();
    }

    /** @return array<string, mixed> */
    public function retrieveConversationModel(string $id): array
    {
        return $this->client->conversations->getModels()[$id]->retrieve();
    }

    /**
     * @param array<string, mixed> $config
     * @return array<string, mixed>
     */
    public function updateConversationModel(string $id, array $config): array
    {
        return $this->client->conversations->getModels()[$id]->update($config);
    }

    /** @return array<string, mixed> */
    public function deleteConversationModel(string $id): array
    {
        return $this->client->conversations->getModels()[$id]->delete();
    }

    // Infrastructure

    /** @return array<string, mixed> */
    public function health(): array
    {
        return $this->client->health->retrieve();
    }

    /** @return array<string, mixed> */
    public function debug(): array
    {
        return $this->client->debug->retrieve();
    }

    public function getClient(): Client
    {
        return $this->client;
    }

    public function isClusterEnabled(): bool
    {
        return $this->clusterEnabled;
    }

    /** @return array<string, mixed> */
    private function withRetry(callable $fn): array
    {
        $delay = 1_000_000;
        for ($i = 0; $i < 3; $i++) {
            try {
                return $fn();
            } catch (\Exception $e) {
                if ($i === 2) {
                    throw $e;
                }
                usleep($delay);
                $delay *= 2;
            }
        }

        throw new \RuntimeException('Unreachable');
    }
}
