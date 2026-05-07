<?php

declare(strict_types=1);

namespace Micka17\TypesenseBundle\Service;

class SynonymSetManager
{
    public function __construct(private readonly TypesenseClient $client)
    {
    }

    /**
     * @param array<string, mixed> $config
     * @return array<string, mixed>
     */
    public function upsertSynonymSet(string $name, array $config): array
    {
        return $this->client->upsertSynonymSet($name, $this->normalizeSynonymSetConfig($config));
    }

    /**
     * @return array<string, mixed>
     */
    public function listSynonymSets(): array
    {
        return $this->client->listSynonymSets();
    }

    /**
     * @return array<string, mixed>
     */
    public function retrieveSynonymSet(string $name): array
    {
        return $this->client->retrieveSynonymSet($name);
    }

    /**
     * @return array<string, mixed>
     */
    public function deleteSynonymSet(string $name): array
    {
        return $this->client->deleteSynonymSet($name);
    }

    /**
     * @param array<string, array<string, mixed>> $synonymSets
     * @return array<string, mixed>
     */
    public function applyConfiguredSynonymSets(array $synonymSets): array
    {
        $results = [];

        foreach ($synonymSets as $name => $config) {
            $results[$name] = $this->upsertSynonymSet($name, $config);
        }

        return $results;
    }

    /**
     * @param array<string, mixed> $config
     * @return array<string, mixed>
     */
    private function normalizeSynonymSetConfig(array $config): array
    {
        if (!isset($config['items'])) {
            return $config;
        }

        $items = [];

        foreach ($config['items'] as $id => $item) {
            $normalizedItem = [
                'id' => (string) $id,
                'synonyms' => $item['synonyms'],
            ];

            if (array_key_exists('root', $item) && $item['root'] !== null) {
                $normalizedItem['root'] = $item['root'];
            }

            $items[] = $normalizedItem;
        }

        return ['items' => $items];
    }
}
