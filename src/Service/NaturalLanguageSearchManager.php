<?php

declare(strict_types=1);

namespace Micka17\TypesenseBundle\Service;

class NaturalLanguageSearchManager
{
    public function __construct(private readonly TypesenseClient $client) {}

    /**
     * @param array<string, array<string, mixed>> $models
     * @return array<string, mixed>
     */
    public function applyConfiguredModels(array $models): array
    {
        $results = [];
        foreach ($models as $id => $config) {
            $config['id'] ??= (string) $id;
            $results[$id] = $this->client->createNlSearchModel($config);
        }

        return $results;
    }

    /**
     * @return array<string, mixed>
     */
    public function listModels(): array
    {
        return $this->client->listNlSearchModels();
    }

    /**
     * @return array<string, mixed>
     */
    public function retrieveModel(string $id): array
    {
        return $this->client->retrieveNlSearchModel($id);
    }

    /**
     * @param array<string, mixed> $config
     * @return array<string, mixed>
     */
    public function updateModel(string $id, array $config): array
    {
        return $this->client->updateNlSearchModel($id, $config);
    }

    /**
     * @return array<string, mixed>
     */
    public function deleteModel(string $id): array
    {
        return $this->client->deleteNlSearchModel($id);
    }
}
