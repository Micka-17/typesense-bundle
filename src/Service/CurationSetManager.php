<?php

declare(strict_types=1);

namespace Micka17\TypesenseBundle\Service;

class CurationSetManager
{
    public function __construct(private readonly TypesenseClient $client)
    {
    }

    /**
     * @param array<string, mixed> $config
     * @return array<string, mixed>
     */
    public function upsertCurationSet(string $name, array $config): array
    {
        return $this->client->upsertCurationSet($name, $this->normalizeCurationSetConfig($config));
    }

    /**
     * @return array<string, mixed>
     */
    public function listCurationSets(): array
    {
        return $this->client->listCurationSets();
    }

    /**
     * @return array<string, mixed>
     */
    public function retrieveCurationSet(string $name): array
    {
        return $this->client->retrieveCurationSet($name);
    }

    /**
     * @return array<string, mixed>
     */
    public function deleteCurationSet(string $name): array
    {
        return $this->client->deleteCurationSet($name);
    }

    /**
     * @param array<string, array<string, mixed>> $curationSets
     * @return array<string, mixed>
     */
    public function applyConfiguredCurationSets(array $curationSets): array
    {
        $results = [];

        foreach ($curationSets as $name => $config) {
            $results[$name] = $this->upsertCurationSet($name, $config);
        }

        return $results;
    }

    /**
     * @param array<string, mixed> $config
     * @return array<string, mixed>
     */
    private function normalizeCurationSetConfig(array $config): array
    {
        if (!isset($config['items'])) {
            return $config;
        }

        $items = [];

        foreach ($config['items'] as $id => $item) {
            $item['id'] = (string) $id;
            $items[] = $item;
        }

        return ['items' => $items];
    }
}
